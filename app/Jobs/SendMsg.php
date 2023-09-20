<?php

namespace App\Jobs;

use App\GatewayWorker\ReceiveHandleService;
use App\Models\Groups;
use App\Models\MessageRepeater;
use App\Models\TalkSession;
use App\Models\TalkSessionRecord;
use App\Tool\Constant;
use App\Tool\ResponseTrait;
use App\Tool\Utils;
use Exception;
use GatewayClient\Gateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class SendMsg implements ShouldQueue
{
    use Dispatchable , InteractsWithQueue , Queueable , SerializesModels , ResponseTrait;

    private array $params;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    /**
     *
     * User: zmm
     * DateTime: 2023/7/7 15:32
     * @throws Exception
     */
    public function handle()
    {
        $params = $this->params;
        $uuid   = $params['uuid'] ?? '';
        //防止后期异步操作
        defined('APP_ENCRYPT') || define('APP_ENCRYPT',$params['debug'] ?? config('app.debug'));
        unset($params['uuid'],$params['debug']);
        $globalData = Utils::getGlobalDataInstance();

        //写入历史消息表
        if($params['talk_type'] == 1){
            $taskSessionInfo = TalkSession::where(['receiver_id'=>$params['to_uid'],'uid'=>$params['from_uid'],'talk_type'=>$params['talk_type']])->first();
            if(!$taskSessionInfo){
                TalkSession::saveData($params);
            }else{
                TalkSession::where('id',$taskSessionInfo->id)->update(['msg_id'=>$params['id'],'is_top'=>0,'is_disturb'=>0,'is_delete'=>0]);
            }
        }else{
            //群
            $taskSessionInfo = TalkSession::where(['receiver_id'=>$params['to_uid'],'talk_type'=>$params['talk_type']])->first();
            if(!$taskSessionInfo){
                TalkSession::saveData($params);
            }else{
                TalkSession::where('id',$taskSessionInfo->id)->update(['msg_id'=>$params['id'],'uid'=>$params['from_uid']]);
            }
        }
        //更新删除会话框记录表
        $recordInfo = TalkSessionRecord::query()->where(['to_uid'=>$params['to_uid'],'from_uid'=>$params['from_uid'],'talk_type'=>$params['talk_type']])->first();
        if($recordInfo){
            //更新标记为为删除
            TalkSessionRecord::query()->where('id',$recordInfo->id)->delete();
        }

        //todo 写入消息重发表
        if($params['talk_type'] == 1){//私聊
              MessageRepeater::saveData($params);
        }else{//群聊
            $groupId     = Utils::getGroupKey($params['to_uid']);
            $groupMember = $globalData->{$groupId};
            if($groupMember){
                $allowSendUid = [];
                foreach ($groupMember as $uid => $joinTime) {
                    $params['created_at'] >= $joinTime && $allowSendUid[] = $uid;
                }
                //去除发送方
                if($allowSendUid){
                    $allowSendUid = array_values(array_diff($allowSendUid, array($params['from_uid'])));
                }
                foreach ($allowSendUid as $v){
                    MessageRepeater::saveData(['id'=>$params['id'],'talk_type'=>$params['talk_type'],'to_uid'=>$v]);
                }
            }

        }

        if ($params['talk_type'] == Constant::TALK_PRIVATE) {
            // 先发送再看离线消息
            Gateway::sendToUid([$params['to_uid'] , $params['from_uid']] , self::cliSuccess($params , ReceiveHandleService::TALK_MESSAGE , $uuid));
            Log::info('发送私聊消息',[self::cliSuccess($params , ReceiveHandleService::TALK_MESSAGE , $uuid)]);
            if (!Gateway::isUidOnline($params['to_uid'])) {
                Redis::zAdd(Utils::offlineMemberKey() , $params['created_at'] , json_encode([
                    'msg_id'     => $params['id'] ,
                    'to_uid'     => $params['to_uid'] ,
                    'talk_type'  => $params['talk_type'] ,
                    'created_at' => $params['created_at'] ,
                ]));
            }

            Redis::exists(sprintf(Constant::MEMBERS_DEVICE , $params['to_uid'])) && Utils::jsPush($params['from_uid'],$params['to_uid'], $params['message_type']);
        } else {
//            $globalData = Utils::getGlobalDataInstance();
            Gateway::sendToGroup($params['to_uid'] , self::cliSuccess($params , ReceiveHandleService::TALK_MESSAGE ,$uuid));
            Log::info('发送群聊消息',[self::cliSuccess($params , ReceiveHandleService::TALK_MESSAGE , $uuid)]);
            $groupId     = Utils::getGroupKey($params['to_uid']);
            $groupMember = $globalData->{$groupId};
            if ($groupMember) {
                $allowUid = [];
                foreach ($groupMember as $uid => $joinTime) {
                    $params['created_at'] >= $joinTime && $allowUid[] = $uid;
                }
                $uidArr = array_diff($allowUid , array_keys(Gateway::getUidListByGroup($params['to_uid'])));
                if ($uidArr) {
                    // 记录消息id
                    Redis::zAdd(Utils::offlineGroupKey() , $params['created_at'] , json_encode([
                        'msg_id'     => $params['id'] ,
                        'to_uid'     => $params['to_uid'] ,
                        'created_at' => $params['created_at'] ,
                        'talk_type'  => $params['talk_type'] ,
                        'uid'        => array_values($uidArr) ,
                    ]));
                }
            } else {
                $uidArr = Groups::query()->from('groups' , 'g')->join('groups_member as m' , 'g.id' , '=' ,
                    'm.group_id')->where(['g.is_dismiss' => 0 , 'g.id' => $params['to_uid']])->where('m.created_at' ,
                    '<=' , date('Y-m-d H:i:s' , $params['created_at']))->get(['m.uid'])->toArray();
                // 记录消息id
                $uidArr && Redis::zAdd(Utils::offlineGroupKey() , $params['created_at'] , json_encode([
                    'msg_id'     => $params['id'] ,
                    'to_uid'     => $params['to_uid'] ,
                    'created_at' => $params['created_at'] ,
                    'talk_type'  => $params['talk_type'] ,
                    'uid'        => array_column($uidArr , 'uid') ,
                ]));
            }
            Utils::jsPush($params['from_uid'],$params['to_uid'],$params['message_type'],Constant::TALK_GROUP);

            unset($uidArr , $params);
        }
    }
}
