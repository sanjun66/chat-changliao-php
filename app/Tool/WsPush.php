<?php


namespace App\Tool;


use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WsPush
{
    public static function handle($method , ...$args)
    {
        $result = [];
        try {
            $args[] = md5($method . config('websocket.http.checkCode'));
            if (method_exists(WsPush::class , $method)) {
                $result = json_decode(call_user_func_array([WsPush::class , $method] , $args) , 1);
            }
        } catch (\Throwable $e) {
        }
        Log::info($method , ['args' => $args , 'res' => $result]);

        return $result['res'] ?? null;
    }

    /**
     * 向所有客户端或者client_id_array指定的客户端发送$send_data数据。如果指定的$client_id_array中的client_id不存在则自动丢弃
     * User: zmm
     * DateTime: 2023/6/14 10:34
     * @param $data
     * @param $sign
     * @return string
     */
    public static function sendToAll($data , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$data] , 'sign' => $sign])->body();
    }

    /**
     * 向客户端client_id发送$send_data数据。如果client_id对应的客户端不存在或者不在线则自动丢弃发送数据
     * User: zmm
     * DateTime: 2023/6/14 10:34
     * @param $client_id
     * @param $data
     * @param $sign
     * @return string
     */
    public static function sendToClient($client_id , $data , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$client_id , $data] , 'sign' => $sign])->body();
    }

    /**
     * 断开与client_id对应的客户端的连接
     * User: zmm
     * DateTime: 2023/6/14 10:35
     * @param $client_id
     * @param $message
     * @param $sign
     * @return string
     */
    public static function closeClient($client_id , $message , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$client_id , $message] , 'sign' => $sign])->body();
    }

    /**
     * 是否在线取决于对应client_id是否触发过onClose回调。
     * User: zmm
     * DateTime: 2023/6/14 10:35
     * @param $client_id
     * @param $sign
     * @return string
     */
    public static function isOnline($client_id , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$client_id] , 'sign' => $sign])->body();
    }

    /**
     * 将client_id与uid绑定，以便通过Gateway::sendToUid($uid)发送数据，通过Gateway::isUidOnline($uid)用户是否在线。
     * User: zmm
     * DateTime: 2023/6/14 10:36
     * @param $client_id
     * @param $uid
     * @param $sign
     * @return string
     */
    public static function bindUid($client_id , $uid , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$client_id , $uid] , 'sign' => $sign])->body();
    }

    /**
     * 判断$uid是否在线
     * User: zmm
     * DateTime: 2023/6/14 10:36
     * @param $uid
     * @param $sign
     * @return string
     */
    public static function isUidOnline($uid , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$uid] , 'sign' => $sign])->body();
    }

    /**
     * 返回一个数组，数组元素为与uid绑定的所有在线的client_id。如果没有在线的client_id则返回一个空数组。
     * User: zmm
     * DateTime: 2023/6/14 10:37
     * @param $uid
     * @param $sign
     * @return string
     */
    public static function getClientIdByUid($uid , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$uid] , 'sign' => $sign])->body();
    }

    /**
     * 将client_id与uid解绑。
     * 注意：当client_id下线（连接断开）时会自动与uid解绑，开发者无需在onClose事件调用Gateway::unbindUid。
     * User: zmm
     * DateTime: 2023/6/14 10:37
     * @param $client_id
     * @param $uid
     * @param $sign
     * @return string
     */
    public static function unbindUid($client_id , $uid , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$client_id , $uid] , 'sign' => $sign])->body();
    }

    /**
     * 向uid绑定的所有在线client_id发送数据。注意：默认uid与client_id是一对多的关系，如果当前uid下绑定了多个client_id，则多个client_id对应的客户端都会收到消息，这类似于PC QQ和手机QQ同时在线接收消息。
     * User: zmm
     * DateTime: 2023/6/14 10:38
     * @param  array | string |int  $uid
     * @param $data
     * @param $sign
     * @return string
     */
    public static function sendToUid(array|string|int $uid , $data , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$uid , $data] , 'sign' => $sign])->body();
    }

    /**
     * 将client_id加入某个组，以便通过Gateway::sendToGroup发送数据。
     * 可以通过Gateway::getClientSessionsByGroup($group)获得该组所有在线成员数据。
     * 可以通过Gateway::getClientCountByGroup($group)获得该组所有在线连接数（人数）。
     * User: zmm
     * DateTime: 2023/6/14 10:40
     * @param $client_id
     * @param $group
     * @param $sign
     * @return string
     */
    public static function joinGroup($client_id , $group , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$client_id , $group] , 'sign' => $sign])->body();
    }

    /**
     * 取消分组，或者说解散分组。
     * 取消分组后所有属于这个分组的用户的连接将被移出分组，此分组将不再存在，除非再次调用Gateway::joinGroup($client_id, $group)将连接加入分组。
     * User: zmm
     * DateTime: 2023/6/14 10:41
     * @param $groupId
     * @param $sign
     * @return string
     */
    public static function ungroup($groupId , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$groupId] , 'sign' => $sign])->body();
    }

    /**
     * 向某个分组的所有在线client_id发送数据。
     * User: zmm
     * DateTime: 2023/6/14 10:41
     * @param $group
     * @param $data
     * @param $sign
     * @return string
     */
    public static function sendToGroup($group , $data , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$group , $data] , 'sign' => $sign])->body();
    }

    /**
     * 将client_id从某个组中删除，不再接收该分组广播(Gateway::sendToGroup)发送的数据。
     * 注意：当client_id下线（连接断开）时，client_id会自动从它所属的各个分组中删除，也就是说无需在onClose回调中调用Gateway::leaveGroup
     * User: zmm
     * DateTime: 2023/6/14 10:42
     * @param $client_id
     * @param $group
     * @param $sign
     * @return string
     */
    public static function leaveGroup($client_id , $group , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$client_id , $group] , 'sign' => $sign])->body();
    }

    /**
     * 获取某分组当前在线成连接数（多少client_id在线）。
     * User: zmm
     * DateTime: 2023/6/14 10:42
     * @param $group
     * @param $sign
     * @return string
     */
    public static function getClientCountByGroup($group , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$group] , 'sign' => $sign])->body();
    }

    /**
     * 获取某个分组所有在线client_id信息。
     * User: zmm
     * DateTime: 2023/6/14 10:43
     * @param $group
     * @param $sign
     * @return string
     */
    public static function getClientSessionsByGroup($group , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$group] , 'sign' => $sign])->body();
    }

    /**
     * 获取当前在线连接总数（多少client_id在线）。
     * User: zmm
     * DateTime: 2023/6/14 10:50
     * @param $sign
     * @return string
     */
    public static function getAllClientCount($sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [] , 'sign' => $sign])->body();
    }

    /**
     * 获取当前所有在线client_id信息。
     * User: zmm
     * DateTime: 2023/6/14 10:51
     * @param $sign
     * @return string
     */
    public static function getAllClientSessions($sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [] , 'sign' => $sign])->body();
    }

    /**
     * 设置某个client_id对应的session。如果对应client_id已经下线或者不存在，则会被忽略。
     * 不要$_SESSION赋值与Gateway::setSession同时操作同一个$client_id，可能会造成session值与预期效果不符。操作当前用户用$_SESSION['xx']=xxx方式赋值即可，操作其他用户session可以使用Gateway::setSession接口。
     * User: zmm
     * DateTime: 2023/6/14 10:51
     * @param $client_id
     * @param $session
     * @param $sign
     * @return string
     */
    public static function setSession($client_id , $session , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$client_id , $session] , 'sign' => $sign])->body();
    }

    /**
     * 更新某个client_id对应的session。如果对应client_id已经下线或者不存在，则会被忽略。
     * User: zmm
     * DateTime: 2023/6/14 10:52
     * @param $client_id
     * @param $session
     * @param $sign
     * @return string
     */
    public static function updateSession($client_id , $session , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$client_id , $session] , 'sign' => $sign])->body();
    }

    /**
     * 获取某个client_id对应的session。
     * User: zmm
     * DateTime: 2023/6/14 10:52
     * @param $client_id
     * @param $sign
     * @return string
     */
    public static function getSession($client_id , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$client_id] , 'sign' => $sign])->body();
    }

    /**
     * 获取某个分组所有在线uid列表。
     * User: zmm
     * DateTime: 2023/6/14 10:52
     * @param $groupId
     * @param $sign
     * @return string
     */
    public static function getUidListByGroup($groupId , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$groupId] , 'sign' => $sign])->body();
    }

    /**
     * 获取某个分组所有在线client_id列表。
     * User: zmm
     * DateTime: 2023/6/14 10:52
     * @param $groupId
     * @param $sign
     * @return string
     */
    public static function getClientIdListByGroup($groupId , $sign) : string
    {
        return Http::asJson()->timeout(1)->post(config('websocket.http.ip') . ':' . config('websocket.http.port') ,
            ['method' => __FUNCTION__ , 'args' => [$groupId] , 'sign' => $sign])->body();
    }
}
