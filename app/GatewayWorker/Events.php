<?php


namespace App\GatewayWorker;


use App\Tool\Aes;
use App\Tool\Constant;
use App\Tool\Jwt;
use App\Tool\ResponseTrait;
use App\Tool\TalkEventConstant;
use App\Tool\Utils;
use Exception;
use GatewayWorker\Lib\Gateway;
use GlobalData\Client as GlobalData;
use Illuminate\Support\Facades\Log;
use Workerman\Connection\TcpConnection;
use Workerman\MySQL\Connection as Mysql;
use Workerman\Protocols\Http\Request;
use Workerman\Redis\Client as Redis;
use Workerman\Timer;
use Workerman\Worker;


class Events
{
    use ResponseTrait;

    private static Redis $redis;
    private static Mysql $db;
    private static GlobalData $globalData;

    /**
     * 设置Worker子进程启动时的回调函数，每个子进程启动时都会执行。
     * User: zmm
     * DateTime: 2023/5/25 10:39
     * @param  Worker  $worker
     * @throws Exception
     */
    public static function onWorkerStart(Worker $worker)
    {
        /**
         * mysql链接上
         */
        self::$db = new Mysql(
            env('DB_HOST'),
            env('DB_PORT'),
            env('DB_USERNAME'),
            env('DB_PASSWORD'),
            env('DB_DATABASE'),
            'utf8mb4'
        );
        /**
         * redis链接上
         */
        self::$redis = new Redis('redis://' . env('REDIS_HOST') . ':' . env('REDIS_PORT'));
        $password    = env('REDIS_PASSWORD');
        $password && self::$redis->auth($password);
        /**
         * 全局变量注册上
         */
        self::$globalData = new GlobalData(config('websocket.globalData.ip') . ':' . config('websocket.globalData.port'));
        /**
         * 后门通知
         */
        $httpWorker            = new Worker('http://' . config('websocket.http.ip') . ':' . config('websocket.http.port'));
        $code                  = config('websocket.http.checkCode');
        $auth                  = config('websocket.http.checkAuth');
        $httpWorker->reusePort = true;
        $httpWorker->onMessage = function (TcpConnection $connection, Request $request) use ($code, $auth) {
            // 请求的方法 sendToAll
            $method = $request->post('method');
            // 请求方法的参数 ["{\"type\":\"broadcast\",\"content\":\"hello all\"}"]
            $args = $request->post('args');
            // sign = md5($method.$code)
            $sign = $request->post('sign');
            if ($auth && $sign !== md5($method . $code)) {
                return $connection->send(json_encode(['code' => 401, 'message' => 'error', 'res' => null]));
            }
            if (method_exists(Gateway::class, $method)) {
                return $connection->send(json_encode([
                    'code'    => 200,
                    'message' => 'success',
                    'res'     => call_user_func_array(
                        [Gateway::class, $method],
                        $args
                    ),
                ]));
            }

            return $connection->send(json_encode(['code' => 422, 'message' => 'error', 'res' => null]));
        };
        $httpWorker->listen();
        /**
         * 只需要操作一次
         */
        if ($worker->id === 0) {
            Timer::add(1, function () {
                /**
                 * 清理一次数据
                 *
                 */
                self::$db->update('members_token')->cols(['client_id' => ''])->query();
                /**
                 * 群信息注册到全局变量中
                 */
                $total     = self::$db->single("select count(*) as count from `groups`  where (`is_dismiss` = '0');");
                $pageSize  = 10;
                $totalPage = ceil($total / $pageSize);
                for ($i = 1; $i <= $totalPage; $i++) {
                    $offset   = ($i - 1) * $pageSize;
                    $groupArr = self::$db->query("select id from `groups`  where (`is_dismiss` = '0')  limit {$offset},{$pageSize};");
                    if ($groupArr) {
                        $result = self::$db->query("select `g`.`id`,`m`.`uid`,`m`.`created_at` from `groups` as `g` join `groups_member` as `m` on `g`.`id` = `m`.`group_id` where (`g`.`is_dismiss` = '0') and  `g`.`id` in (" . join(
                            ',',
                            array_column($groupArr, 'id')
                        ) . ")");
                        $tmpArr = [];
                        foreach ($result as $groupMember) {
                            $tmpArr[$groupMember['id']][$groupMember['uid']] = strtotime($groupMember['created_at']);
                        }
                        foreach ($tmpArr as $k => $v) {
                            self::$globalData->add(Utils::getGroupKey($k), $v);
                        }
                        unset($result, $tmpArr, $groupArr);
                    }
                }
            }, [], false);
        } else if ($worker->id === 1) {
            /**
             * 私聊定时器 处理离线消息
             */
            Timer::add(1.2, function () {
                $time = time();
                self::$redis->zRangeByScore(
                    Utils::offlineMemberKey(),
                    0,
                    $time,
                    [],
                    function ($result, $redis) use ($time) {
                        if (!$result) {
                            return;
                        }
                        $str = '';
                        foreach ($result as $v) {
                            $tmpArr = json_decode($v, 1);
                            $str    .= '(' . join(
                                ',',
                                [
                                    $tmpArr['msg_id'],
                                    $tmpArr['to_uid'],
                                    $tmpArr['talk_type'],
                                    $tmpArr['created_at'],
                                ]
                            ) . '),';
                        }
                        $sql = "INSERT INTO `friends_offline_message` (`msg_id`, `to_uid`, `talk_type`, `created_at`) VALUES";
                        self::$db->query($sql . rtrim($str, ',') . ';');
                        $redis->zRemRangeByScore(Utils::offlineMemberKey(), 0, $time);
                        unset($str, $sql, $time, $result);
                    }
                );
            });
        } else if ($worker->id === 2) {
            /**
             * 群组定时器 处理离线消息
             */
            Timer::add(1.5, function () {
                $time = time();
                self::$redis->zRangeByScore(
                    Utils::offlineGroupKey(),
                    0,
                    $time,
                    [],
                    function ($result, $redis) use ($time) {
                        if (!$result) {
                            return;
                        }
                        $str = '';
                        foreach ($result as $v) {
                            $tmpArr = json_decode($v, 1);
                            foreach ($tmpArr['uid'] as $to_uid) {
                                $str .= '(' . join(
                                    ',',
                                    [
                                        $tmpArr['msg_id'],
                                        $to_uid,
                                        $tmpArr['talk_type'],
                                        $tmpArr['created_at'],
                                    ]
                                ) . '),';
                            }
                        }
                        $sql = "INSERT INTO `friends_offline_message` (`msg_id`, `to_uid`, `talk_type`, `created_at`) VALUES";
                        self::$db->query($sql . rtrim($str, ',') . ';');
                        $redis->zRemRangeByScore(Utils::offlineGroupKey(), 0, $time);
                        unset($str, $sql, $time, $result);
                    }
                );
            });
        } else if ($worker->id === 3) {
            /**
             * 定时器 处理 私聊/群聊 未读离线消息
             */
            Timer::add(1.8, function () {
                $time = time();
                self::$redis->zRangeByScore(
                    Utils::offlineReadMemberKey(),
                    0,
                    $time,
                    [],
                    function ($result, $redis) use ($time) {
                        if (!$result) {
                            return;
                        }
                        $str = '';
                        foreach ($result as $v) {
                            $tmpArr = json_decode($v, 1);
                            $change = "'" . join("','", array_values($tmpArr)) . "'";
                            $str    .= '(' . $change . '),';
                        }
                        $sql = "INSERT INTO `read_offline_message` (`from_uid`, `to_uid`, `talk_type`, `created_at`,`msg_ids`) VALUES";
                        self::$db->query($sql . rtrim($str, ',') . ';');
                        $redis->zRemRangeByScore(Utils::offlineReadMemberKey(), 0, $time);
                        unset($str, $sql, $time, $result);
                    }
                );
            });
        } else if ($worker->id === 4) {
            /**
             * 群组定时器 处理群离线已读消息
             */
            //            Timer::add(2.0 , function () {
            //                $time = time();
            //                self::$redis->zRangeByScore(Utils::offlineReadGroupKey() , 0 , $time , [] ,
            //                    function ($result , $redis) use ($time) {
            //                        if (!$result) {
            //                            return;
            //                        }
            //                        $str = '';
            //                        foreach ($result as $v) {
            //                            $tmpArr = json_decode($v , 1);
            //                            $change = "'" . join("','", array_values($tmpArr) ) . "'";
            //                            $str    .= '(' . $change . '),';
            //                        }
            //                        $sql = "INSERT INTO `read_offline_message` (`from_uid`, `to_uid`, `talk_type`, `created_at`,`msg_ids`) VALUES";
            //                        self::$db->query($sql . rtrim($str , ',') . ';');
            //                        $redis->zRemRangeByScore(Utils::offlineReadGroupKey() , 0 , $time);
            //                        unset($str , $sql , $time , $result);
            //                    });
            //
            //            });
        }
        /**
         * mysql 心跳
         */
        Timer::add(120, function () {
            self::$db->row('select 1');
        });
    }

    /**
     * 链接websocket时
     * User: zmm
     * DateTime: 2023/5/25 10:55
     * @param $client_id
     * @param $httpBuffer
     * @throws Exception
     */
    public static function onWebSocketConnect($client_id, $httpBuffer)
    {
        $_SESSION['app_encrypt'] = $httpBuffer['get']['app_encrypt'] ?? 1;
        // 没有登录直接拒绝
        if (empty($httpBuffer['get']['token'])) {
            Gateway::closeClient(
                $client_id,
                self::cliError(
                    Constant::CODE_ARR[Constant::CODE_401],
                    TalkEventConstant::EVENT_SYSTEM,
                    Constant::CODE_401
                )
            );

            return;
        }
        // 有jwt校验失败拒绝
        $result = Jwt::verifyToken($httpBuffer['get']['token']);
        if (!$result) {
            Gateway::closeClient(
                $client_id,
                self::cliError(
                    Constant::CODE_ARR[Constant::CODE_402],
                    TalkEventConstant::EVENT_SYSTEM,
                    Constant::CODE_402
                )
            );

            return;
        }
        $membersToken = self::$db->select()->from('members_token')->where("uid=:uid and platform=:platform")->bindValues([
            'uid'      => $result['uid'],
            'platform' => $result['platform'],
        ])->row();
        // 不存在可能登录过期了
        if (!$membersToken) {
            Gateway::closeClient(
                $client_id,
                self::cliError(
                    Constant::CODE_ARR[Constant::CODE_402],
                    TalkEventConstant::EVENT_SYSTEM,
                    Constant::CODE_402
                )
            );

            return;
        }
        // 签名的值不等于 可能其他设备登录了
        if ($membersToken['crc32'] != crc32($httpBuffer['get']['token'])) {
            Gateway::closeClient(
                $client_id,
                self::cliError(
                    Constant::CODE_ARR[Constant::CODE_403],
                    TalkEventConstant::EVENT_SYSTEM,
                    Constant::CODE_403
                )
            );

            return;
        }
        // 获取绑定的平台id 踢掉旧的设备
        if ($membersToken['client_id']) {
            Gateway::isOnline($membersToken['client_id']) && Gateway::closeClient(
                $membersToken['client_id'],
                self::cliError(
                    Constant::CODE_ARR[Constant::CODE_403],
                    TalkEventConstant::EVENT_SYSTEM,
                    Constant::CODE_403
                )
            );
        }
        if (strtotime($membersToken['expire_at']) < time()) {
            Gateway::closeClient(
                $client_id,
                self::cliError(
                    Constant::CODE_ARR[Constant::CODE_401],
                    TalkEventConstant::EVENT_SYSTEM,
                    Constant::CODE_401
                )
            );

            return;
        }
        // 绑定设备
        Gateway::bindUid($client_id, $result['uid']);
        // 更新在线状态
        self::$db->beginTrans();
        self::$db->update('members')->cols(['state' => 1])->where("id='{$result['uid']}'")->query();
        self::$db->update('members_token')->cols(['client_id' => $client_id])->where("id='{$membersToken['id']}'")->query();
        // 加入群聊
        $groupList = self::$db->query("select `g`.`id` from `groups` as `g` inner join `groups_member` as `m` on `g`.`id` = `m`.`group_id` where (`g`.`is_dismiss` = '0' and `m`.`uid` = '{$result['uid']}')");
        // 添加登录
        $_SESSION['login_id'] = self::$db->insert('members_connect')->cols(array_filter([
            'uid'        => $result['uid'],
            'platform'   => $result['platform'],
            'login_ip'   => $httpBuffer['server']['HTTP_X_REAL_IP'] ?? $httpBuffer['server']['HTTP_REAL_IP'] ?? '0.0.0.0',
            'login_date' => date('Y-m-d H:i:s'),
        ]))->query();
        self::$db->commitTrans();


        // 获取用户好友
        $friends_list = self::$db->select('friend_id')->from('friends')->where("uid='{$result['uid']}'")->query();
        foreach ($friends_list as $val){
                //在线
                Gateway::sendToUid($val['friend_id'] ,
                    self::cliReadSuccess(['from_uid' => $result['uid'] , 'extra' => ['type'=>1]],'online_state_change'));
                Log::info("上线通知好友",[self::cliReadSuccess(['from_uid' => $result['uid'] , 'extra' => ['type'=>1]],'online_state_change')]);
        }

        Gateway::sendToClient($client_id ,
            self::cliSuccess(['client_id' => $client_id , 'uid' => $result['uid'] , 'message' => 'welcome to '.$result['uid']]));
        foreach ($groupList as $groupId) {
            Gateway::joinGroup($client_id, $groupId['id']);
        }
        unset($result, $groupList, $membersToken);
    }

    /**
     * 当客户端通过连接发来数据时
     * User: zmm
     * DateTime: 2023/5/25 10:40
     * @param $clientId
     * @param $message
     */
    public static function onMessage($clientId, $message)
    {
        Log::info('客户端消息 ' . $clientId, [$message]);
        // 解密消息
        if (empty($_SESSION['app_encrypt'])) {
            $message = (new Aes())->decrypt($message);
        }
        $result = json_decode($message, 1);
        if (empty($result['event_name'])) {
            Gateway::sendToClient($clientId, self::cliError('event_name 不存在'));

            return;
        }
        /**
         * 客户端数据全局变量
         */
        $_SESSION['event_name'] = $result['event_name'];
        $_SESSION['uuid']       = $result['uuid'] ?? '';

        if ('heartbeat' == $result['event_name']) {
            Gateway::sendToClient($clientId, self::cliSuccess(null, $result['event_name'], $result['uuid'] ?? ''));
            return;
        }
        if (!method_exists(ReceiveHandleService::class, $result['event_name'])) {
            Gateway::sendToClient($clientId, self::cliError('event_name 不存在'));

            return;
        }

        call_user_func_array(
            [ReceiveHandleService::class, $result['event_name']],
            [$clientId, $result, self::$db, self::$redis, self::$globalData]
        );
    }

    /**
     * 客户端与Gateway进程的连接断开时触发。不管是客户端主动断开还是服务端主动断开，都会触发这个回调
     * User: zmm
     * DateTime: 2023/5/25 10:42
     * @param $clientId
     */
    public static function onClose($clientId)
    {
        self::$db->beginTrans();
        $membersToken = self::$db->select()->from('members_token')->where("client_id=:client_id")->bindValues([
            'client_id' => $clientId,
        ])->row();
        if (!$membersToken) {
            self::$db->commitTrans();

            return;
        }
        self::$db->update('members_token')->cols(['client_id' => ''])->where("id='{$membersToken['id']}'")->query();
        // 更新登出时间
        isset($_SESSION['login_id']) && self::$db->query("update `members_connect` set `logout_date` = '" . date('Y-m-d H:i:s') . "' where id = '{$_SESSION['login_id']}';");
        if (!Gateway::isUidOnline($membersToken['uid'])) {
            self::$db->update('members')->cols(['state' => 0])->where("id='{$membersToken['uid']}'")->query();
            $friends_list = self::$db->select('friend_id')->from('friends')->where("uid='{$membersToken['uid']}'")->query();
            foreach ($friends_list as $val){
                //离线通知好友
                Gateway::sendToUid($val['friend_id'] ,
                    self::cliReadSuccess(['from_uid' => $membersToken['uid'] , 'extra' => ['type'=>0]],'online_state_change'));
                Log::info("离线通知好友",[self::cliReadSuccess(['from_uid' => $membersToken['uid'] , 'extra' => ['type'=>0]],'online_state_change')]);
            }
        }
        self::$db->commitTrans();
    }
}
