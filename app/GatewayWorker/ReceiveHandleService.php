<?php


namespace App\GatewayWorker;

use App\Models\FriendsApply;
use App\Models\GroupsMember;
use App\Models\Members;
use App\Models\MessageRepeater;
use App\Models\TalkRecordsAudio;
use App\Models\TalkRecordsFile;
use App\Models\TalkRecordsForward;
use App\Models\TalkRecordsFriend;
use App\Models\TalkRecordsInvite;
use App\Tool\Constant;
use App\Tool\ResponseTrait;
use App\Tool\Utils;
use GatewayClient\Gateway;
use Illuminate\Support\Facades\Log;

class ReceiveHandleService
{
    use ResponseTrait;

    const TALK_MESSAGE = 'talk_message'; // 聊天推送
    const TALK_REVOKE = 'talk_revoke';   // 消息撤回
    const TALK_READ = 'talk_read'; // 消息已读

    /**
     * 登录拉去离线消息
     * User: zmm
     * DateTime: 2023/6/8 15:26
     * @param $clientId
     * @param $params
     * @param $db
     * @param $redis
     * @param $globalData
     */
    public static function talk_pull($clientId , $params , $db , $redis , $globalData)
    {
        $uid = Gateway::getUidByClientId($clientId);
//        self::readOfflineMessage($uid,$clientId,$db);
        // 客户端确认ack
        if (!empty($params['ids']) && $globalData->add($uid . $params['ids'] , $clientId)) {
            $db->query("delete from `friends_offline_message` where to_uid = '{$uid}' and `msg_id` in (" . join(',' ,
                    array_unique(explode(',' , trim($params['ids'])))) . ");");
            unset($globalData->{$uid . $params['ids']});

            return;
        }
        // 设置用户分页
        $key = __FUNCTION__ . $uid;
        if ($globalData->add($key , $clientId)) {
            $pageSize = Utils::offlinePageSize();
            $total    = $db->single("select count(*) as count from `friends_offline_message` where `to_uid` = '{$uid}'");
            if (!$total) {
                unset($globalData->{$key});
                Gateway::sendToClient($clientId , self::cliSuccess([]));

                return;
            }
            $totalPage = ceil($total / $pageSize);
            for ($i = 1 ; $i <= $totalPage ; $i++) {
                $offset   = ($i - 1) * $pageSize;
                $msgIdArr = $db->query("select `msg_id` from `friends_offline_message` where to_uid = '{$uid}' limit {$offset},{$pageSize};");
                $idStr    = join(',' , array_column($msgIdArr , 'msg_id'));
                //如果在重试表的话 重试表的标记为state=1
                $db->query("UPDATE `message_repeater` SET state = 1 WHERE recipient_id='{$uid}' AND msg_id in ({$idStr}) and state in(0,2)");
                $result   = $db->query("select pwd,id,is_revoke,from_uid,to_uid,talk_type,quote_id,message_type,message,created_at,`timestamp`,warn_users from friends_message where id in ({$idStr})");
                Gateway::sendToUid($uid , self::cliSuccess(self::messageStructHandle($result)));
            }
            //加入丢失消息发送
            $total1    = $db->single("select count(*) as count from `message_repeater` where `recipient_id` = '{$uid}' and state in(0,2)");
            $totalPage1 = ceil($total1 / $pageSize);
            for ($i = 1 ; $i <= $totalPage1 ; $i++) {
                $offset   = ($i - 1) * $pageSize;
                $msgIdArr = $db->query("select `msg_id` from `message_repeater` where recipient_id = '{$uid}' and state in(0,2) limit {$offset},{$pageSize};");
                $idArr = array_column($msgIdArr , 'msg_id');
                if($idArr){
                    $idStr    = join(',' , $idArr);
                    $result   = $db->query("select pwd,id,is_revoke,from_uid,to_uid,talk_type,quote_id,message_type,message,created_at,`timestamp`,warn_users from friends_message where id in ({$idStr})");
                    Gateway::sendToUid($uid , self::cliSuccess(self::messageStructHandle($result)));
                    //标记为state=1
                    $db->query("UPDATE `message_repeater` SET state = 1 WHERE recipient_id='{$uid}' AND msg_id in ({$idStr})");
                }
            }
            // 释放锁
            unset($globalData->{$key});
        } else {
            Gateway::sendToClient($clientId , self::cliError("操作频繁，请稍后再试"));
        }
    }


    public static function talk_read($clientId , $params , $db , $redis , $globalData)
    {
        $uid = Gateway::getUidByClientId($clientId);
        // 客户端确认ack
        if (!empty($params['ids'])) {
            $db->query("delete from `read_offline_message` where from_uid = '{$uid}' and `id` in (" . join(',' ,
                    array_unique(explode(',' , trim($params['ids'])))) . ");");
            // unset($globalData->{$uid . $params['ids']});
            return;
        }
        $total_read    = $db->single("select count(*) as count from `read_offline_message` where `from_uid` = '{$uid}' and `is_pull` = 0");
        if (!$total_read) {
            //   unset($globalData->{$key});
//            Gateway::sendToClient($clientId , self::cliSuccess([]));
            return;
        }
        // 设置用户分页
        $pageSize = Utils::offlinePageSize();
        $totalPage_read = ceil($total_read / $pageSize);
        for ($i = 1 ; $i <= $totalPage_read ; $i++) {
            $offset   = ($i - 1) * $pageSize;
            $msgIdArr = $db->query("select `id`,`msg_ids`,`to_uid`,`created_at`,`from_uid`,`talk_type` from `read_offline_message` where `from_uid` = '{$uid}' and `is_pull` = 0 limit {$offset},{$pageSize};");
            foreach ($msgIdArr as $val){
                Gateway::sendToUid($uid, self::cliReadSuccess([
                    'id' => $val['id'],
                    'from_uid' => $val['from_uid'],
                    'created_at' => $val['created_at'],
                    'talk_type' => $val['talk_type'],
                    'to_uid' => $val['to_uid'],
                ],
                    ReceiveHandleService::TALK_READ, explode(',', $val['msg_ids'])));
                $db->update('read_offline_message')->cols(['is_pull' => 1])->where("id='{$val['id']}'")->query();
                Log::info('发送已读离线消息', [
                    self::cliReadSuccess([
                        'id' => $val['id'],
                        'from_uid' => $val['from_uid'],
                        'created_at' => $val['created_at'],
                        'talk_type' => $val['talk_type'],
                        'to_uid' => $val['to_uid'],
                    ],
                        ReceiveHandleService::TALK_READ, explode(',', $val['msg_ids']))
                ]);
            }
        }
    }

    /**
     * desc:消息回执
     * author: mxm
     * Time: 2023/9/8   10:11
     */
    public static function msgReceipt($clientId , $params , $db , $redis , $globalData)
    {
        $uid = Gateway::getUidByClientId($clientId);
        $msg_id = $params['msg_id'];
        $info = $db->row("SELECT * FROM `message_repeater` WHERE recipient_id='{$uid}' AND msg_id = '{$msg_id}' and state in(0,2)");

        if($info){
            $db->update('message_repeater')->cols(['state' => 1])->where("id='{$info['id']}'")->query();
        }
    }

    /**
     * 消息结构体处理
     * User: zmm
     * DateTime: 2023/6/8 16:53
     * @param  array  $data
     * @return array
     */
    public static function messageStructHandle(array $data) : array
    {
        $apply = $friend = $video = $voice = $files = $forwards = $invites = [];
        foreach ($data as $k => $value) {
            if (isset($value['pwd']) && $value['pwd']) {
                $data[$k]['message']   = '';
                $data[$k]['is_secret'] = true;
                $data[$k]['pwd']       = '';
                continue;
            } else {
                $data[$k]['is_secret'] = false;
                $data[$k]['pwd']       = '';
            }
            switch ($value['message_type']) {
                case Constant::FILE_MESSAGE:
                    $files[] = $value['id'];
                    break;
                case Constant::FORWARD_MESSAGE:
                    $forwards[] = $value['id'];
                    break;
                case Constant::GROUP_INVITE_MESSAGE:
                    $invites[] = $value['id'];
                    break;
                case Constant::VOICE_MESSAGE:
                    $voice[] = $value['id'];
                    break;
                case Constant::VIDEO_MESSAGE:
                    $video[] = $value['id'];
                    break;
                case Constant::FRIEND_MESSAGE:
                    $friend[] = $value['id'];
                    break;
                case Constant::FRIEND_APPLY_MESSAGE:
                    $apply[] = $value['id'];
                    break;
                default:
            }
        }
        // 文件消息
        if ($files) {
            $files = TalkRecordsFile::query()->whereIn('record_id' , $files)->get([
                'cover' ,
                'suffix' ,
                'original_name' ,
                'type' ,
                'size' ,
                'path' ,
                'url' ,
                'record_id' ,
                'weight' ,
                'height' ,
                'duration' ,
                'driver',
            ])->keyBy('record_id')->toArray();
            foreach ($files as $k => $v) {
                $files[$k]['url']   = Utils::getAvatarUrl($v['url'] , $v['driver']);
                $files[$k]['cover'] = Utils::getAvatarUrl($v['cover'] , $v['driver']);
            }
        }
        // 转发消息
        if ($forwards) {
            $forwards = TalkRecordsForward::query()->whereIn('record_id' , $forwards)->get([
                'records_id' ,
                'text' ,
                'record_id' ,
            ])->keyBy('record_id')->toArray();
        }
        // 退群入群消息
        if ($invites) {
            $invites = TalkRecordsInvite::query()->whereIn('record_id' , $invites)->get([
                'type' ,
                'operate_user_id' ,
                'user_ids' ,
                'record_id' ,
            ])->keyBy('record_id')->toArray();
        }
        // 语音消息
        if ($voice) {
            $voice = TalkRecordsAudio::query()->whereIn('record_id' , $voice)->get([
                'record_id' ,
                'state' ,
                'begin_time' ,
                'duration' ,
                'user_ids' ,
                'updated_at' ,
            ])->keyBy('record_id')->toArray();
        }
        // 视频消息
        if ($video) {
            $video = TalkRecordsAudio::query()->whereIn('record_id' , $video)->get([
                'record_id' ,
                'state' ,
                'begin_time' ,
                'duration' ,
                'user_ids' ,
                'updated_at' ,
            ])->keyBy('record_id')->toArray();
        }
        // 添加好友消息
        if ($friend) {
            $friend = TalkRecordsFriend::query()->whereIn('record_id' , $friend)->get([
                'record_id' ,
                'state' ,
                'process_message' ,
            ])->keyBy('record_id')->toArray();
        }
        // 好友申请消息
        if ($apply) {
            $apply = FriendsApply::query()->whereIn('record_id' , $apply)->get([
                'record_id' ,
                'process_message' ,
                'state' ,
                'remark' ,
            ])->keyBy('record_id')->toArray();
        }
        foreach ($data as $k => $row) {
            $data[$k]['created_at'] = isset($row['created_at'][11]) ? strtotime($row['created_at']) : $row['created_at'];
            $data[$k]['extra']      = (object) [];
            $data[$k]['warn_users'] = (string) $row['warn_users'] ?? '';
            if (!empty($row['is_secret'])) {
                continue;
            }
            if (!empty($row['is_revoke'])) {
                $data[$k]['message'] = '';
                continue;
            }
            switch ($row['message_type']) {
                // 文件消息
                case Constant::FILE_MESSAGE:
                    $data[$k]['extra'] = $files[$row['id']] ?? (object) [];
                    break;
                // 转发消息
                case Constant::FORWARD_MESSAGE:
                    if (isset($forwards[$row['id']])) {
                        $data[$k]['extra'] = [
//                            'num'  => substr_count($forwards[$row['id']]['records_id'] , ',') + 1 ,
                            'talk_list' => is_array($forwards[$row['id']]['text']) ? $forwards[$row['id']]['text']['talk_list'] : json_decode($forwards[$row['id']]['text'] ,
                                1)['talk_list'] ,
                            'talk_type' => is_array($forwards[$row['id']]['text']) ? $forwards[$row['id']]['text']['talk_type'] : json_decode($forwards[$row['id']]['text'] ,
                                1)['talk_type']
                        ];
                    }
                    break;
                // 入群消息 退群消息 踢群消息
                case Constant::GROUP_INVITE_MESSAGE:
                    if (isset($invites[$row['id']])) {
                        $data[$k]['extra'] = [
                            'type'              => $invites[$row['id']]['type'] ,
                            'operate_user_id'   => $invites[$row['id']]['operate_user_id'] ,
                            'operate_user_name' => Members::getCacheInfo($invites[$row['id']]['operate_user_id'])['nick_name'] ,
                        ];
                        // 入群通知 自动退群
                        if (in_array($data[$k]['extra']['type'] , [1 , 2])) {
                            $data[$k]['extra']['users'] = $invites[$row['id']]['user_ids'];
                            // 3:管理员踢群;4群解散
                        } else if (in_array($data[$k]['extra']['type'] , [3 , 4])) {
                            $data[$k]['extra']['users'] = $invites[$row['id']]['user_ids'];
                        }
                        $data[$k]['extra']['users'] = Members::query()->whereIn('id' ,
                            explode(',' , $data[$k]['extra']['users']))->get(['nick_name' , 'id'])->toArray();
                    }
                    break;
                // 音频消息
                case Constant::VOICE_MESSAGE:
                    if (isset($voice[$row['id']])) {
                        $data[$k]['extra'] = [
                            'state'    => $voice[$row['id']]['state'] ,
                            'duration' => $voice[$row['id']]['duration'] ,
                            'user_ids' => $voice[$row['id']]['user_ids'] ,
                            'nickname' => (function ($row) {
                                if ($row['talk_type'] != Constant::TALK_PRIVATE) {
                                    return GroupsMember::query()->where([
                                        'uid'      => $row['from_uid'] ,
                                        'group_id' => $row['to_uid'] ,
                                    ])->value('notes');
                                }

                                return '';
                            })($row) ,
                        ];
                    }
                    break;
                // 视频消息
                case Constant::VIDEO_MESSAGE:
                    if (isset($video[$row['id']])) {
                        $data[$k]['extra'] = [
                            'state'    => $video[$row['id']]['state'] ,
                            'duration' => $video[$row['id']]['duration'] ,
                            'user_ids' => $video[$row['id']]['user_ids'] ,
                            'nickname' => (function ($row) {
                                if ($row['talk_type'] != Constant::TALK_PRIVATE) {
                                    return GroupsMember::query()->where([
                                        'uid'      => $row['from_uid'] ,
                                        'group_id' => $row['to_uid'] ,
                                    ])->value('notes');
                                }

                                return '';
                            })($row) ,
                        ];
                    }
                    break;
                // 添加好友消息
                case Constant::FRIEND_MESSAGE:
                    if (isset($friend[$row['id']])) {
                        $data[$k]['extra'] = [
                            'state'           => $friend[$row['id']]['state'] ,
                            'process_message' => $friend[$row['id']]['process_message'] ,
                        ];
                    }
                    break;
                // 好友申请消息
                case Constant::FRIEND_APPLY_MESSAGE:
                    if (isset($apply[$row['id']])) {
                        $from              = Members::getCacheInfo($row['from_uid']);
                        $to                = Members::getCacheInfo($row['to_uid']);
                        $data[$k]['extra'] = array_merge($apply[$row['id']] , (array) $data[$k]['extra'] , [
                            'from_nick_name' => $from['nick_name'] ,
                            'to_nick_name'   => $to['nick_name'] ,
                            'from_avatar'    => $from['avatar'] ,
                            'to_avatar'      => $to['avatar'] ,
                        ]);
                    }
                    break;
            }
        }

        return $data;
    }
}
