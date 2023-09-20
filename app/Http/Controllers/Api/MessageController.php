<?php


namespace App\Http\Controllers\Api;

use App\GatewayWorker\ReadHandleService;
use App\GatewayWorker\ReceiveHandleService;
use App\Http\Controllers\Controller;
use App\Jobs\SendMsg;
use App\Models\Friends;
use App\Models\FriendsMessage;
use App\Models\GroupsMember;
use App\Models\Members;
use App\Models\MessageRead;
use App\Models\MessageRepeater;
use App\Models\TalkRecordsFile;
use App\Models\TalkRecordsForward;
use App\Tool\Constant;
use App\Tool\Utils;
use Exception;
use GatewayClient\Gateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use zjkal\TimeHelper;

class MessageController extends Controller
{

    /**
     * 消息撤回
     * User: zmm
     * DateTime: 2023/6/5 14:10
     * @param  Request  $request
     * @return JsonResponse
     * @throws Exception
     */
    public function msgRevoke(Request $request) : JsonResponse
    {
        $request->validate(['id' => 'bail|required|int|min:1']);
        $lock = Cache::lock(__FUNCTION__ . $request['id'] , 1);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }
        $messageModel = FriendsMessage::query()->where([
            'id'       => $request['id'] ,
            'from_uid' => $request['uid'] ,
        ])->firstOr(['*'] , function () {
            throw new BadRequestHttpException(__("消息撤回失败"));
        });
        $messageModel->setAttribute('is_revoke' , 1);
        $messageModel->save();
        $time = time();
        // 私聊
        if ($messageModel['talk_type'] == Constant::TALK_PRIVATE) {
            foreach ([$messageModel['from_uid'] , $messageModel['to_uid']] as $uid) {
                if (Gateway::isUidOnline($uid)) {
                    Gateway::sendToUid($uid ,
                        self::cliSuccess([
                            'id'        => $request['id'] ,
                            'from_uid'  => $request['uid'] ,
                            'talk_type' => $messageModel['talk_type'] ,
                            'to_uid'    => $messageModel['to_uid'] ,
                        ] ,
                            ReceiveHandleService::TALK_REVOKE , $request['uuid'] ?? null));
                } else {
                    Redis::zadd(Utils::offlineMemberKey() , $time , json_encode([
                        'msg_id'     => $messageModel['id'] ,
                        'to_uid'     => $uid ,
                        'talk_type'  => $messageModel['talk_type'] ,
                        'created_at' => $time ,
                    ]));
                }
            }
            // 群聊
        } else {
            Gateway::sendToGroup($messageModel['to_uid'] ,
                self::cliSuccess([
                    'id'        => $request['id'] ,
                    'from_uid'  => $request['uid'] ,
                    'talk_type' => $messageModel['talk_type'] ,
                    'to_uid'    => $messageModel['to_uid'] ,
                ] ,
                    ReceiveHandleService::TALK_REVOKE ,
                    $request['uuid'] ?? null));
            $groupId     = Utils::getGroupKey($messageModel['to_uid']);
            $groupMember = Utils::getGlobalDataInstance()->{$groupId};
            if ($groupMember) {
                $uidArr = array_diff(array_keys($groupMember) ,
                    array_keys(Gateway::getUidListByGroup($messageModel['to_uid'])));
                if ($uidArr) {
                    Redis::zadd(Utils::offlineGroupKey() , $time , json_encode([
                        'msg_id'     => $messageModel['id'] ,
                        'to_uid'     => $messageModel['to_uid'] ,
                        'created_at' => $time ,
                        'talk_type'  => $messageModel['talk_type'] ,
                        'uid'        => array_values($uidArr) ,
                    ]));
                }
            }
        }
        $lock->release();

        return $this->responseSuccess(['id' => $request['id'] , 'from_uid' => $request['uid']]);
    }

    /**
     * 消息转发
     * User: zmm
     * DateTime: 2023/6/15 18:18
     * @param  Request  $request
     * @return JsonResponse
     * @throws Exception
     */
    public function msgForward(Request $request) : JsonResponse
    {
        $request->validate([
            'ids'       => 'bail|required|string' ,
            'to_uid'    => 'bail|required|int|min:1' ,
            'talk_type' => 'bail|required|int|in:1,2' ,
            'pwd'       => 'bail|nullable|string|min:4|max:16' ,
            'forward_type' => 'bail|required|int|in:1,2',
        ]);
        $idArr = parse_ids($request['ids']);
        if (count($idArr) > 50) {
            throw new BadRequestHttpException(__("最多只能转发50条对话消息"));
        }
        $lock = Cache::lock(__FUNCTION__ . $request['uid'] . $request['ids'] , 3);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁，请稍后再试"));
        }
        // 判断是否好友群关系
        Friends::isFriendOrGroupMember($request['uid'] , $request['to_uid'] , $request['talk_type']);
        // 消息列表
        $messageList = FriendsMessage::query()->whereIn('id' ,
            $idArr)->whereIn('message_type' ,
            [Constant::TEXT_MESSAGE , Constant::FILE_MESSAGE , Constant::FORWARD_MESSAGE])->orderBy('id')->get([
            'from_uid' ,
            'to_uid' ,
            'created_at' ,
            'timestamp' ,
            'message_type' ,
            'message' ,
            'talk_type' ,
            'id' ,
        ])->toArray();

        if (!$messageList) {
            throw new BadRequestHttpException(__("转发消息不能为空"));
        }
        //todo 查文件信息
        // $fileList = TalkRecordsFile::query()->whereIn('record_id' ,
        //     $idArr)->orderBy('id')->get()->keyBy('record_id')->toArray();
        // $talkType = 1;
        // $talkList = [];
        // foreach ($messageList as $v) {
        //     $talkType = $v['talk_type'];
        //     if($request['forward_type'] == 1){
        //         // unset($v['id']);
        //     }else{
        //         // unset($v['id'] , $v['talk_type']);
        //     }
        //     $talkList[] = $v;
        // }
        $date = date('Y-m-d H:i:s');
        $new_idArr = [];
        DB::beginTransaction();
        if($request['forward_type'] == Constant::FORWARD_SINGLE){
            foreach ($messageList as $val){
                $old_id = $val['id'];
                $val['from_uid'] = $request['uid'];
                $val['to_uid'] = $request['to_uid'];
                $val['created_at'] = $date;
                $val['timestamp'] = TimeHelper::getMilliTimestamp();
                $val['pwd'] = $request['pwd'] ?? '';
                $val['talk_type'] = $request['talk_type'];
                unset($val['id']);
                $recordId  = FriendsMessage::query()->insertGetId($val);
                if($val['message_type'] == 2){
                    //如果是文件消息
                    $this->doFile($old_id,$recordId,$request['uid']);
                }
                if($val['message_type'] == 3){
                    //如果是转发消息
                    $this->doForward($old_id,$recordId,$request['uid'],$request['talk_type']);
                }
                $new_idArr[] = $recordId;
            }
        }
        //合并转发
        if($request['forward_type'] == Constant::FORWARD_MERGE){
            $Files = $this->getFileInfo(array_column($messageList , 'id'));

            $recordId  = FriendsMessage::query()->insertGetId([
                'from_uid'     => $request['uid'] ,
                'to_uid'       => $request['to_uid'] ,
                'talk_type'    => $request['talk_type'] ,
                'message_type' => Constant::FORWARD_MESSAGE ,
                'created_at'   => $date ,
                'timestamp'    => TimeHelper::getMilliTimestamp() ,
                'pwd'          => $request['pwd'] ?? '' ,
            ]);

            foreach ($messageList as $key =>$val){
                $messageList[$key]['extra'] = [];
                if($val['message_type'] == Constant::FILE_MESSAGE){
                    $messageList[$key]['extra'] = $Files[$val['id']];
                }
                //增加发送人姓名和头像
                $user = Members::select('nick_name','avatar','driver')->where('id',$val['from_uid'])->first();
                $messageList[$key]['from_name'] = $user->nick_name;
                $messageList[$key]['from_avatar'] = Utils::getAvatarUrl($user->avatar,$user->driver);
            }
            $source_talk_type = FriendsMessage::query()->whereIn('id' , $idArr)->value('talk_type');

            $forwardData = [
                'talk_type'  => $request['talk_type'] ,
                'records_id' => join(',' , array_column($messageList , 'id')) ,
                'uid'        => $request['to_uid'] ,
                'record_id'  => $recordId ,
                'text'       => json_encode([
                    'talk_list' => $messageList ,
//                    'source'    => ['to_uid' => $request['to_uid'] , 'from_uid' => $request['uid']] ,
                    'talk_type' => $source_talk_type,//
                ] , JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ,
                'forward_type' => Constant::FORWARD_MERGE ,
            ];
            TalkRecordsForward::query()->insert($forwardData);
        }
        DB::commit();

        $time = strtotime($date);

        if($request['forward_type'] == Constant::FORWARD_SINGLE){
            //获取消息ID数量

            $result = FriendsMessage::whereIn('id',$new_idArr)->select('pwd','is_read','id','is_revoke','from_uid','to_uid','talk_type','quote_id','message_type','message','created_at','timestamp','warn_users')->orderBy('id')->get()->toArray();
            // Gateway::sendToUid($request['to_uid'] , self::cliSuccess(ReceiveHandleService::messageStructHandle($result)));
            $data = ReceiveHandleService::messageStructHandle($result);
            foreach ($data as $key=> $val){
                $val['uuid'] = $request['uuid'] ?? null;
                $val['debug'] = APP_ENCRYPT;
                dispatch(new SendMsg($val))->onQueue('SendMsg');
            }
        }

        if($request['forward_type'] == Constant::FORWARD_MERGE){
            $data = ReceiveHandleService::messageStructHandle([
                [
                    'id'           => $recordId ,
                    'from_uid'     => $request['uid'] ,
                    'to_uid'       => $request['to_uid'] ,
                    'talk_type'    => $request['talk_type'] ,
                    'quote_id'     => 0 ,
                    'message_type' => Constant::FORWARD_MESSAGE ,
                    'created_at'   => $time ,
                    'warn_users'   => '' ,
                    'message'      => '' ,
                    'timestamp'    => TimeHelper::getMilliTimestamp() ,
                    'pwd'          => $request['pwd'] ,
                ] ,
            ]);
            $data = array_pop($data);

            dispatch(new SendMsg($data + [
                    'uuid'  => $request['uuid'] ?? null ,
                    'debug' => APP_ENCRYPT ,
                ]))->onQueue('SendMsg');

            return $this->responseSuccess($data + ['uuid' => $request['uuid'] ?? null]);
        }

        $lock->release();

        return $this->responseSuccess();
    }

    /**
     * desc:转发消息的时候，copy文件消息
     * author: mxm
     * Time: 2023/9/6   17:29
     * @param $msg_id
     * @param $new_msg_id
     * @param $from_uid
     */
    public function doFile($msg_id, $new_msg_id, $from_uid){
        $info = TalkRecordsFile::where('record_id',$msg_id)->first();
        $extra = [
            'driver'        => intval($info->driver ?? 0),
            'uid'           => $from_uid ,
            'suffix'        => $info->suffix ,
            'original_name' => $info->original_name ,
            'type'          => $info->type ,
            'size'          => $info->size ?? 0 ,
            'url'           => $info->url ,
            'path'          => $info->path ?? '' ,
            'height'        => $info->height ?? 0 ,
            'weight'        => $info->weight ?? 0 ,
            'duration'      => $info->duration ?? 0 ,
            'cover'         => $info->cover ?? '',
            'created_at'    => time() ,
        ];
        TalkRecordsFile::query()->insert(['record_id'  => $new_msg_id , 'created_at' => date('Y-m-d H:i:s')] + $extra);
    }

    /**
     * copy 合并消息记录
     */
    public function doForward($msg_id, $new_msg_id, $from_uid,$talk_type){
        $info = TalkRecordsForward::where('record_id',$msg_id)->first();

        $forwardData = [
            'talk_type'  => $talk_type,
            'records_id' => $info->records_id ,
            'uid'        => $from_uid ,
            'record_id'  => $new_msg_id ,
            'text'       => json_encode($info->text),
            'forward_type' => Constant::FORWARD_SINGLE ,
        ];
        TalkRecordsForward::query()->insert($forwardData);
    }

    /**
     * desc:合并消息的时候，获得文件信息
     * author: mxm
     * Time: 2023/9/6   18:39
     * @param $files
     * @return array
     */
    public function getFileInfo($files): array
    {
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
        return $files;
    }

    /**
     * 发送消息
     * User: zmm
     * DateTime: 2023/7/7 14:55
     * @param  Request  $request
     * @return JsonResponse
     */
    public function msgSend(Request $request) : JsonResponse
    {
        $params = [
            'to_uid'     => 'bail|required|int|min:1' ,
            'quote_id'   => 'bail|nullable|int|min:0' ,
            'message'    => 'bail|required|string|max:1024' ,
            'warn_users' => 'bail|nullable|string|max:512' ,
            'talk_type'  => 'bail|required|int|in:1,2' ,
            'pwd'        => 'bail|nullable|string|min:4|max:16' ,
            'extra'      => 'bail|nullable|json' ,
        ];
        $request->validate($params);
        Friends::isFriendOrGroupMember($request['uid'] , $request['to_uid'] , $request['talk_type']);
        $data  = [
            'from_uid'     => $request['uid'] ,
            'to_uid'       => $request['to_uid'] ,
            'talk_type'    => $request['talk_type'] ,
            'quote_id'     => $request['quote_id'] ?? 0 ,
            'message'      => $request['message'] ,
            'message_type' => Constant::TEXT_MESSAGE ,
            'warn_users'   => $request['warn_users'] ?? '' ,
            'created_at'   => date('Y-m-d H:i:s') ,
            'timestamp'    => TimeHelper::getMilliTimestamp() ,
            'pwd'          => $request['pwd'] ?? '' ,
        ];
        $extra = [];
        if ($request['extra']) {
            $result = json_decode($request['extra'] , 1);
            $validator = Validator::make($result,[
                'suffix'        => 'bail|required|string|max:30' ,
                'original_name' => 'bail|required|string|max:300' ,
                'type'          => 'bail|required|int|min:1|max:4' ,
                'url'           => 'bail|required|string|max:300' ,
                'driver'        => 'bail|nullable|int|min:0|max:3' ,
            ]);
            if ($validator->fails()) {
                throw new BadRequestHttpException($validator->errors()->first(),null,422);
            }
            $extra = [
                'driver'        => intval($result['driver'] ?? 0),
                'uid'           => $request['uid'] ,
                'suffix'        => $result['suffix'] ,
                'original_name' => $result['original_name'] ,
                'type'          => $result['type'] ,
                'size'          => $result['size'] ?? 0 ,
                'url'           => $result['url'] ,
                'path'          => $result['path'] ?? '' ,
                'height'        => $result['height'] ?? 0 ,
                'weight'        => $result['weight'] ?? 0 ,
                'duration'      => $result['duration'] ?? 0 ,
                'cover'         => $result['cover'] ?? '',
                'created_at'    => time() ,
            ];
            $data['message_type'] = Constant::FILE_MESSAGE;
        }
        DB::beginTransaction();
        $data['id'] = FriendsMessage::query()->insertGetId($data);
        $extra && TalkRecordsFile::query()->insert(['record_id'  => $data['id'] , 'created_at' => date('Y-m-d H:i:s')] + $extra);
        DB::commit();
        $sendData = ReceiveHandleService::messageStructHandle([$data]);
        $sendData = array_pop($sendData);

        $sendData += ['uuid' => $request['uuid'] ?? null , 'debug' => APP_ENCRYPT];
        dispatch(new SendMsg($sendData))->onQueue('SendMsg');

        unset($sendData['debug']);

        return $this->responseSuccess($sendData);
    }

    /**
     * 解密消息
     * User: zmm
     * DateTime: 2023/7/27 18:14
     * @param  Request  $request
     * @return JsonResponse
     */
    public function msgDecrypt(Request $request) : JsonResponse
    {
        $params = [
            'id'  => 'bail|required|int|min:1' ,
            'pwd' => 'bail|required|string|min:4|max:16' ,
        ];
        $request->validate($params);
        $msgModel = FriendsMessage::query()->findOr($request['id'] , ['*'] , function () {
            throw new BadRequestHttpException(__("解密消息参数错误"));
        });
        if ($request['pwd'] !== $msgModel['pwd']) {
            throw new BadRequestHttpException(__("解密密码错误"));
        }
        unset($msgModel['pwd']);
        $sendData = ReceiveHandleService::messageStructHandle([$msgModel->toArray()]);
        $sendData = array_pop($sendData);

        return $this->responseSuccess(array_merge($sendData , [
            'uuid'      => $request['uuid'] ?? '' ,
            'pwd'       => $request['pwd'] ,
            'is_secret' => true ,
        ]));
    }

    /**
     * desc: 聊天记录查找
     * author: mxm
     * Time: 2023/8/21   11:46
     */
    public function getHistoryList(Request $request){
        //todo condition 0-全部 1-日期 '2023-08-21'  2-文件，type是群的话，多一个按 3-群成员检索
        //todo type区分是私人 群
        $params = [
            'uid' => 'bail|required|int|min:1',
            'to_uid'  => 'bail|required|int|min:1', // type=2 to_uid为群id
            'type' => 'bail|required|int|in:1,2',
            'condition'=> 'bail|required|int|in:0,1,2,3,4',
            'keys' => 'bail|nullable|string'
        ];
        $request->validate($params);
        $limit = 20;

        if($request['condition'] == 0 && $request['keys'] == ''){
            throw new BadRequestHttpException(__("请输入关键词"));
        }

        $where = [];
        if($request['type'] == 1){
            //私聊
            $where['talk_type'] = 1;
            $where['from_uid'] = $request['uid'];
            $where['to_uid'] = $request['to_uid'];

            if ($request['condition'] == 2){
                $where['message_type'] = '2';
            }
        }else{
            //群聊
            $where['talk_type'] = 2;
            $where['to_uid'] = $request['to_uid'];
            switch ($request['condition']){
                case 2:
                    $where['message_type'] = '2';
                case 3:
                    $where['from_uid'] = $request['keys'];
            }
        }
        if($request['condition'] > 0){
            // 按条件查
            if($request['condition'] == '1'){
                $list = FriendsMessage::where($where)->whereDate('created_at',$request['keys'])->orderByDesc('id')->paginate($limit)->toArray();
            }else{
                $list = FriendsMessage::where($where)->orderByDesc('id')->paginate($limit)->toArray();
            }
        }else{
            // 没条件
            if($request['keys'] != ''){
                $list = FriendsMessage::where('message','like',"%".$request['keys']."%")->where($where)->orderByDesc('id')->paginate($limit)->toArray();
            }else{
                $list = FriendsMessage::where($where)->orderByDesc('id')->paginate($limit)->toArray();
            }
        }
        return $this->responseSuccess($list);

    }

    /**
     * desc:设置消息已读
     * author: mxm
     * Time: 2023/8/21   15:21
     * JsonResponse
     */
    public function setMessageRead(Request $request): JsonResponse
    {
        $params = [
            'message_id'  => 'bail|required|int|min:1',
            'uid' => 'bail|nullable|int|min:1', // 群成员ID
            'talk_type' => 'bail|required|int|in:1,2',
        ];
        $request->validate($params);

        if($request['talk_type'] == '2' && $request['uid'] == ''){
            throw new BadRequestHttpException(__("群成员ID不存在"));
        }
        $message_info = FriendsMessage::where('id',$request['message_id'])->select('from_uid','to_uid','message_type','id')->first();
        if(!$message_info->id){
            throw new BadRequestHttpException(__("消息ID不存在"));
        }
        $from_uid = $message_info->from_uid;//发送方
        $to_uid = $message_info->to_uid;//接收方/群ID

        // 未读消息ID
        $weiArr = FriendsMessage::where(['from_uid'=>$from_uid,'to_uid'=>$to_uid,'is_read'=>0,'talk_type'=>$request['talk_type']])->where('id','<=',$request['message_id'])->pluck('id')->toArray();
        $time = time();

        // 单聊
        if($request['talk_type'] == Constant::TALK_PRIVATE && $weiArr){
            FriendsMessage::whereIn('id',$weiArr)->update(['is_read'=>1]);
        }
        // 群聊
        if($request['talk_type'] == Constant::TALK_GROUP){
            //上次查看到的群消息ID
            $last_read_id = GroupsMember::where(['group_id'=>$to_uid,'uid'=>$request['uid']])->value('last_read_id');
            if($last_read_id != $request['message_id']) {
                if (!$last_read_id) {
                    $weiGroupArr = FriendsMessage::where([
                        'to_uid' => $to_uid, 'talk_type' => $request['talk_type']
                    ])->where('id', '<=', $request['message_id'])->orderByDesc('id')->pluck('id')->toArray();
                } else {
                    $weiGroupArr = FriendsMessage::where([
                        'to_uid' => $to_uid, 'talk_type' => $request['talk_type']
                    ])->where('id', '<=', $request['message_id'])->where('id', '>', $last_read_id)->orderByDesc('id')->pluck('id')->toArray();
                }
                foreach ($weiGroupArr as $key => $val) {
                    //去查询这个人这些消息有没有读过
                    $is_read = MessageRead::isRead($val, $request['uid']);
                    if ($is_read > 0) {
                        unset($weiGroupArr[$key]);
                    }
                }
                //剩下是没读过的 给他设置成读过的
                foreach ($weiGroupArr as $val) {
                    MessageRead::insert(['member_id' => $request['uid'], 'message_id' => $val]);
                    //todo 每个消息id的发送者 相当于都被人看了一遍
                }
                // 再查一下 哪些是未读
                if (!$last_read_id) {
                    $noReadArr = FriendsMessage::where([
                        'to_uid' => $to_uid, 'talk_type' => $request['talk_type']
                    ])->where('id', '<=', $request['message_id'])->where('is_read',0)->orderByDesc('id')->pluck('id')->toArray();
                } else {
                    $noReadArr = FriendsMessage::where([
                        'to_uid' => $to_uid, 'talk_type' => $request['talk_type']
                    ])->where('id', '<=', $request['message_id'])->where('id', '>', $last_read_id)->where('is_read',0)->orderByDesc('id')->pluck('id')->toArray();
                }
                FriendsMessage::whereIn('id', $noReadArr)->update(['is_read' => 1]);
                GroupsMember::where([
                    'group_id' => $to_uid, 'uid' => $request['uid']
                ])->update(['last_read_id' => $request['message_id']]);
                $weiArr = $noReadArr;
            }
        }
        if($weiArr){
            // 发消息给发送者
            if (Gateway::isUidOnline($from_uid)) {
                $message = self::cliReadSuccess([
                    'id'    => $request['message_id'] ,
                    'from_uid'  => $from_uid,
                    'talk_type' => $request['talk_type'] ,
                    'to_uid'    => $to_uid,
                ] ,
                    ReceiveHandleService::TALK_READ , $weiArr);
                Gateway::sendToUid($from_uid ,$message);
            } else {
                Redis::zadd(Utils::offlineReadMemberKey() , $time , json_encode([
                    'from_uid'   => $from_uid,
                    'to_uid'     => $to_uid ,
                    'talk_type'  => $request['talk_type'] ,
                    'created_at' => $time ,
                    'msg_ids' => implode(',',$weiArr)
                ]));
            }
        }
        return $this->responseSuccess();

    }

    /**
     * desc:获取群组已读未读明细
     * author: mxm
     * Time: 2023/8/21   16:23
     */
    public function getReadList(Request $request){
        $params = [
            'message_id'  => 'bail|required|int|min:1',
            'talk_type' => 'bail|required|int|in:1,2',
        ];
        $request->validate($params);
        $message_info = FriendsMessage::where('id',$request['message_id'])->select('to_uid','from_uid','id')->first();
        $group_id = $message_info->to_uid;
        $uid = $message_info->from_uid;
        $list = MessageRead::getReadNum($request['message_id'],$group_id,$uid);
        return $this->responseSuccess($list);
    }

    /**
     * desc:消息回执
     * author: mxm
     * Time: 2023/9/8   10:11
     */
    public function msgReceipt(Request $request): JsonResponse
    {
        $params = [
            'ids'  => 'bail|required|string|max:100' ,
            'msg_id'   => 'bail|required|int|min:1' ,
            'talk_type'  => 'bail|required|int|in:1,2' ,
        ];
        $request->validate($params);
        $idArr = parse_ids($request['ids']);

        if($idArr){
            foreach ($idArr as $val){
                $info = MessageRepeater::where(['recipient_id'=>$val,'msg_id'=>$request['msg_id'],'talk_type'=>$request['talk_type']])->whereIn('state',[0,2])->first();

                if($info){
                    MessageRepeater::where('id',$info->id)->update(['state'=>1]);
                }
            }
        }


        return $this->responseSuccess();

    }
}
