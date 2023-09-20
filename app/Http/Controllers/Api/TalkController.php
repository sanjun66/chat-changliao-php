<?php


namespace App\Http\Controllers\Api;

use App\GatewayWorker\ReceiveHandleService;
use App\Http\Controllers\Controller;
use App\Models\Friends;
use App\Models\FriendsGroups;
use App\Models\FriendsMessage;
use App\Models\FriendsOfflineMessage;
use App\Models\Groups;
use App\Models\GroupsMember;
use App\Models\Members;
use App\Models\ReadOfflineMessage;
use App\Models\TalkSession;
use App\Models\TalkSessionDelete;
use App\Models\TalkSessionRecord;
use App\Services\SendEmail;
use App\Tool\Constant;
use App\Tool\Utils;
use Couchbase\Group;
use GatewayClient\Gateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class TalkController extends Controller
{
    /**
     * 聊天列表对话框
     * User: zmm
     * DateTime: 2023/5/30 16:20
     * @param  Request  $request
     * @return JsonResponse
     */
    public function talkList(Request $request) : JsonResponse
    {
        $date = date('Y-m-d'); // 获取当前日期
        $newDate = date('Y-m-d', strtotime('-7 days', strtotime($date))); // 将日期减去七天

        //获取单聊的
        $userSingleDelArr = TalkSessionRecord::where(['from_uid'=>$request['uid']])->pluck('to_uid')->toArray();

        //获取群聊的
        $talkGroupArr = GroupsMember::getUserGroup($request['uid']);
        $userDelArr = TalkSessionRecord::where(['from_uid'=>$request['uid'],'talk_type'=>2])->pluck('to_uid')->toArray();
        if($userDelArr && $talkGroupArr){
            //获取没删除过群会话的ID
            $talkGroupArr = array_diff($talkGroupArr,array_unique($userDelArr));
        }
        $talkGroupArr = Groups::getTalkGroupGrep($talkGroupArr,$request['uid']);//过滤掉解散群和退群的
        $groupStr = '('.implode(',',$talkGroupArr).')';
        // 输出减去七天后的日期
//        $info = DB::select("SELECT t2.id as yid, t2.*
//                FROM talk_session t1 LEFT
//                JOIN talk_session t2 ON t1.uid = t2.receiver_id AND t1.receiver_id = t2.uid where t2.uid != {$request['uid']} and t1.updated_at > {$newDate} order by t1.msg_id,t2.msg_id desc"
//        );

        // 会话框单聊数据
        $fa = TalkSession::query()->where(['uid'=>$request['uid'],'talk_type'=>1])->whereDate('updated_at','>',$newDate)->get()->keyBy('id')->toArray();//47 79
        $shou = TalkSession::query()->where(['receiver_id'=>$request['uid'],'talk_type'=>1])->whereDate('updated_at','>',$newDate)->get()->keyBy('id')->toArray();//79 47
        foreach ($fa as $key=> $val){
            foreach ($shou as $k => $v){
                if($val['receiver_id'] == $v['uid']){
                    $max_id = max($val['msg_id'],$v['msg_id']);
                    $zuida_info = TalkSession::query()->where('msg_id',$max_id)->first()->toArray();
                    $notId = TalkSession::query()->where(['uid'=>$zuida_info['receiver_id'],'talk_type'=>1,'receiver_id'=>$zuida_info['uid']])->value('id');//47 79
                    if (array_key_exists($notId, $fa)) {
                        unset($fa[$key]);
                    } else {
                        unset($shou[$k]);
                    }
                }
            }
        }
        $idArr = array_merge(array_keys($fa),array_keys($shou));//符合条件的框

//        dd($talkGroupArr);
//        $ids = array_column($info,'yid');
        $str = '('.implode(',',$idArr).')';
        $listObj = [];
        if($talkGroupArr){
            if($idArr){
                $listObj = DB::select("select id,talk_type,uid,receiver_id,updated_at,msg_id from talk_session where ((id in {$str}) or (receiver_id in {$groupStr} and talk_type =2)) and updated_at > {$newDate} order by msg_id desc");
            }else{
                $listObj = DB::select("select id,talk_type,uid,receiver_id,updated_at,msg_id from talk_session where receiver_id in {$groupStr} and talk_type =2 and updated_at > {$newDate} order by msg_id desc");
            }
        }else{
            if($idArr){
                $listObj = DB::select("select id,talk_type,uid,receiver_id,updated_at,msg_id from talk_session where id in {$str} and updated_at > {$newDate} order by msg_id desc");
            }
        }
        $list = json_decode(json_encode($listObj),true);//只能获得私聊的数据
        // dd($list);
        $talkArr  = $new_list =  [];
        foreach ($list as $k=> $v) {
            if($userSingleDelArr && (in_array($v['uid'],$userSingleDelArr) || in_array($v['receiver_id'],$userSingleDelArr))){
                unset($list[$k]);
            }else{
                $new_list[] = $v;
            }
        }
        $list = $new_list;
        foreach ($list as $k=> $v) {
            if (1 == $v['talk_type']) {
                //删除过会话框的不展示
                // 别人发给我的
                if($v['uid'] != $request['uid']){
                    $aa = $v['uid'];
                }else{
                    $aa = $v['receiver_id'];
                }
                $talkArr[] =  $aa;
            }
            unset($list[$k]['id']);
            // }
        }

        $delMsgIdArr = TalkSessionDelete::query()->where('uid',$request['uid'])->pluck('msg_id')->toArray();//用户删除消息ID的列表
        $groupResArr  = Groups::getTalkGroupInfo($talkGroupArr ,
            [ 'name' , 'avatar' , 'id' , 'driver']);
        foreach ($groupResArr as $key=> $val){
            $groupResArr[$key]['avatar'] = Utils::getAvatarUrl($val['avatar'],$val['driver']);
            unset($groupResArr[$key]['driver']);
        }
        $memberResArr = Members::getInfo($talkArr , ['nick_name as name' , 'avatar' , 'id' , 'driver']);
        foreach ($memberResArr as $key=> $val){
            $memberResArr[$key]['avatar'] = Utils::getAvatarUrl($val['avatar'],$val['driver']);
            unset($memberResArr[$key]['driver']);
        }
        foreach ($list as $k => $v) {
            if (1 == $v['talk_type']) {
                if($v['uid'] != $request['uid']){
                    $aa = $v['uid'];
                }else{
                    $aa = $v['receiver_id'];
                }
                $list[$k] = array_merge($memberResArr[$aa] , $v);

                // 获取消息ID
                if(in_array($v['msg_id'],$delMsgIdArr)){
                    $my_msg_id = FriendsMessage::where(['from_uid'=>$request['uid'],'to_uid'=>$aa,'talk_type'=>1])->whereNotIn('id',$delMsgIdArr)->orderByDesc('id')->value('id');
                    $ta_msg_id = FriendsMessage::where(['from_uid'=>$aa,'to_uid'=>$request['uid'],'talk_type'=>1])->whereNotIn('id',$delMsgIdArr)->orderByDesc('id')->value('id');
                    $msg_id = max($my_msg_id, $ta_msg_id);
                }else{
                    $msg_id = $v['msg_id'];
                }

            } else {
                $aa = $v['receiver_id'];
                $list[$k] = array_merge($groupResArr[$v['receiver_id']] , $v);

                if(in_array($v['msg_id'],$delMsgIdArr)){
                    $msg_id = FriendsMessage::where(['from_uid'=>$request['uid'],'to_uid'=>$aa,'talk_type'=>1])->whereNotIn('id',$delMsgIdArr)->orderByDesc('id')->value('id');
                }else{
                    $msg_id = $v['msg_id'];
                }
            }

            $msgInfo = FriendsMessage::select('timestamp','message','pwd','message_type')->where('id',$msg_id)->first()->toArray();
            $list[$k]['timestamp'] = $msgInfo['timestamp'];
            $list[$k]['message'] = $msgInfo['message'] ?? '';
            $list[$k]['is_pwd'] = $msgInfo['pwd'] ? 1 : 0;
            $list[$k]['message_type'] = $msgInfo['message_type'];

            unset($list[$k]['uid']);
//            unset($list[$k]['msg_id']);
            unset($list[$k]['receiver_id']);
        }

        foreach ($list as $k => $v) {
            $list[$k]['is_disturb'] = Friends::getIsDisturb($request['uid'],$v['id'],$v['talk_type']);
        }

        return $this->responseSuccess($list);


    }

    /**
     * 聊天对话置顶
     * User: zmm
     * DateTime: 2023/6/1 17:17
     * @param  Request  $request
     * @return JsonResponse
     */
    public function talkTop(Request $request) : JsonResponse
    {
        $request->validate(['id' => 'bail|required|int']);

        $talkModel = TalkSession::query()->where(['id' => $request['id'] , 'uid' => $request['uid']])->firstOr(['*'] ,
            function () {
                throw new BadRequestHttpException(__("置顶失败"));
            });
        $talkModel->setAttribute('is_top' , 1);
        $talkModel->setAttribute('top_date' , date('Y-m-d H:i:s'));
        $talkModel->save();

        return $this->responseSuccess();
    }


    /**
     * 新增对话框
     * User: zmm
     * DateTime: 2023/6/5 14:29
     * @param  Request  $request
     * @return JsonResponse
     */
    public function talkCreate(Request $request) : JsonResponse
    {
        $request->validate(['talk_type' => 'bail|required|in:1,2' , 'to_uid' => 'bail|required|int|min:1']);
        $lock = Cache::lock(__FUNCTION__ . $request['talk_type'] . $request['to_uid'] , 2);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }
        try {
            if (Constant::TALK_PRIVATE == $request['talk_type']) {
                if (!Friends::query()->where([
                    'uid'       => $request['uid'] ,
                    'friend_id' => $request['to_uid'] ,
                ])->exists()) {
                    throw new BadRequestHttpException(__("暂不属于好友关系无法进行聊天！"));
                }
            } else {
                if (!GroupsMember::query()->where([
                    'uid'      => $request['uid'] ,
                    'group_id' => $request['to_uid'] ,
                ])->exists()) {
                    throw new BadRequestHttpException(__("暂不属于群聊成员，无法进行聊天！"));
                }
            }
            TalkSession::query()->firstOrCreate([
                'talk_type'   => $request['talk_type'] ,
                'uid'         => $request['uid'] ,
                'receiver_id' => $request['to_uid'] ,
            ] , [
                'talk_type'   => $request['talk_type'] ,
                'uid'         => $request['uid'] ,
                'receiver_id' => $request['to_uid'] ,
            ]);
            $lock->release();
        } catch (BadRequestException $e) {
            $lock->release();
            throw new BadRequestHttpException($e->getMessage());
        }

        return $this->responseSuccess();
    }

    /**
     * 对话框删除
     * User: zmm
     * DateTime: 2023/6/5 14:46
     * @param  Request  $request
     * @return JsonResponse
     */
    public function talkDelete(Request $request) : JsonResponse
    {
        $request->validate(['id' => 'bail|required|int|min:1']);
        $lock = Cache::lock(__FUNCTION__ . $request['id'] , 2);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }
        TalkSession::query()->where(['uid' => $request['uid'] , 'id' => $request['id']])->delete();

        $lock->release();

        return $this->responseSuccess();
    }

    /**
     * 对话框免打扰
     * User: zmm
     * DateTime: 2023/6/5 14:46
     * @param  Request  $request
     * @return JsonResponse
     */
    public function talkDisturb(Request $request) : JsonResponse
    {
        $request->validate(['id' => 'bail|required|int|min:1' , 'is_disturb' => 'bail|required|int|in:0,1']);
        $lock = Cache::lock(__FUNCTION__ . $request['id'] . $request['is_disturb'] , 2);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }
        TalkSession::query()->where([
            'uid'        => $request['uid'] ,
            'id'         => $request['id'] ,
            'is_disturb' => $request['is_disturb'] ,
        ])->delete();
        $lock->release();

        return $this->responseSuccess();
    }

    /**
     * desc:获取云端对话记录
     * author: mxm
     * Time: 2023/8/29   21:17
     */
    public function getChatMessage(Request $request): JsonResponse
    {
        $request->validate([
            // 对方ID 1-用户ID，2-群ID
            'id'     => 'bail|required|int' ,
            // 1-私聊 2-群聊
            'talk_type' => 'bail|required|string' ,
            // 备注
            'messageId'  => 'bail|required|int' ,
        ]);
        $messageId = $request['messageId'];
        $limit = 20;

        // 判断这个人是否删除过会话
        $last_message_id = TalkSessionRecord::where(['from_uid'=>$request['uid'],'to_uid'=>$request['id'],'talk_type'=>$request['talk_type']])->orderByDesc('id')->value('last_message_id');
        // 判断这个人是否删除过消息
        $delete_msgIdArr = TalkSessionDelete::query()->where('uid',$request['uid'])->distinct('msg_id')->pluck('msg_id')->toArray();

        if($request['talk_type'] == Constant::TALK_PRIVATE){
            $msgIdArr = FriendsMessage::query()
                ->Where(function($query) use($request,$last_message_id,$messageId,$delete_msgIdArr){
                    $query->where(['from_uid'=>$request['id'],'to_uid'=>$request['uid']])->where(['talk_type'=>$request['talk_type'],'is_revoke'=>"0"])->whereIn('message_type',[Constant::TEXT_MESSAGE,Constant::FILE_MESSAGE,Constant::FORWARD_MESSAGE,Constant::VOICE_MESSAGE,Constant::VIDEO_MESSAGE])
                        ->when($messageId > 0,function ($query) use($messageId){
                                $query->where('id','<',$messageId);
                        })->when($last_message_id , function ($query) use ($last_message_id) {
                        $query->where('id','>',$last_message_id);
                    })->when(function ($query) use($delete_msgIdArr){
                        $query->whereNotIn('id',$delete_msgIdArr);
                    });
                })
                ->orWhere(function($query) use($request,$last_message_id,$messageId,$delete_msgIdArr){
                    $query->where(['from_uid'=>$request['uid'],'to_uid'=>$request['id']])->where(['talk_type'=>$request['talk_type'],'is_revoke'=>"0"])->whereIn('message_type',[Constant::TEXT_MESSAGE,Constant::FILE_MESSAGE,Constant::FORWARD_MESSAGE,Constant::VOICE_MESSAGE,Constant::VIDEO_MESSAGE])->when($messageId > 0,function ($query) use($messageId){
                        $query->where('id','<',$messageId);
                    })->when($last_message_id , function ($query) use ($last_message_id) {
                        $query->where('id','>',$last_message_id);
                    })->when(function ($query) use($delete_msgIdArr){
                        $query->whereNotIn('id',$delete_msgIdArr);
                    });
                })
                ->limit($limit)
                ->orderByDesc('id')
                ->pluck('id')
                ->toArray();
        }
        if($request['talk_type'] == Constant::TALK_GROUP){
            $msgIdArr = FriendsMessage::query()
                ->Where(function($query) use($request,$last_message_id,$messageId,$delete_msgIdArr){
                    $query->where(['to_uid'=>$request['id']])->where(['talk_type'=>$request['talk_type'],'is_revoke'=>"0"])->when($messageId > 0,function ($query) use($messageId){
                        $query->where('id','<',$messageId);
                    })->whereIn('message_type',[Constant::TEXT_MESSAGE,Constant::FILE_MESSAGE,Constant::FORWARD_MESSAGE])->when($last_message_id , function ($query) use ($last_message_id) {
                        $query->where('id','>',$last_message_id);
                    })->when(function ($query) use($delete_msgIdArr){
                        $query->whereNotIn('id',$delete_msgIdArr);
                    });
                })
                ->limit($limit)
                ->orderByDesc('id')
                ->pluck('id')
                ->toArray();
        }



        $result = FriendsMessage::whereIn('id',$msgIdArr)->select('pwd','is_read','id','is_revoke','from_uid','to_uid','talk_type','quote_id','message_type','message','created_at','timestamp','warn_users')->orderByDesc('id')->get()->toArray();
        $list = ReceiveHandleService::messageStructHandle($result);

        return $this->responseSuccess($list);
    }

    /**
     * desc:获取离线消息
     * author: mxm
     * Time: 2023/9/6   11:18
     * @param  Request  $request
     * @return JsonResponse
     */
    public function talk_pull(Request $request): JsonResponse
    {
        $uid = $request['uid'];

        $lock = Cache::lock( __FUNCTION__ . $uid, 1);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }

        try {
            $page = $request['page'] ?? 1;
            $pageSize = Utils::offlinePageSize();
            $offset   = ($page - 1) * $pageSize;

            // 客户端确认ack
            if (!empty($request['ids'])) {
                DB::table('friends_offline_message')->where('to_uid',$uid)->whereIn('msg_id',array_unique(explode(',' , trim($request['ids']))))->delete();
                return $this->responseSuccess();
            }

            // 设置用户分页
            $total    = DB::table("friends_offline_message")->where('to_uid',$uid)->count();
            if (!$total) {
                return $this->responseSuccess();
            }

            $idArr = FriendsOfflineMessage::where('to_uid',$uid)->offset($offset)
                ->limit($pageSize)->pluck('msg_id')->toArray();
            $result = FriendsMessage::whereIn('id',$idArr)->select('pwd','is_read','id','is_revoke','from_uid','to_uid','talk_type','quote_id','message_type','message','created_at','timestamp','warn_users')->get()->toArray();
            $list = self::cliSuccess(ReceiveHandleService::messageStructHandle($result));

            $lock->release();

            return $this->responseSuccess((json_decode($list,true))['data']);
        } catch (BadRequestException $e) {
            $lock->release();
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /**
     * desc:获取离线已读消息
     * author: mxm
     * Time: 2023/9/6   11:18
     * @param  Request  $request
     * @return JsonResponse
     */
    public function talk_read(Request $request): JsonResponse
    {
        $uid = $request['uid'];
        $data = [];
        $lock = Cache::lock( __FUNCTION__ . $uid, 1);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }

        try {
            $page = $request['page'] ?? 1;
            $pageSize = Utils::offlinePageSize();
            $offset   = ($page - 1) * $pageSize;

            // 客户端确认ack
            if (!empty($request['ids'])) {
                DB::table('read_offline_message')->where('from_uid',$uid)->whereIn('id',array_unique(explode(',' , trim($request['ids']))))->delete();
                return $this->responseSuccess();
            }

            // 设置用户分页
            $total    = DB::table("read_offline_message")->where('to_uid',$uid)->count();
            if (!$total) {
                return $this->responseSuccess();
            }
            $msgIdArr = ReadOfflineMessage::select('id','msg_ids','to_uid','created_at','from_uid','talk_type')->where(['from_uid'=>$uid,'is_pull'=>0])->offset($offset)
                ->limit($pageSize)->get()->toArray();

            foreach ($msgIdArr as $val){
                $list = self::cliReadSuccess([
                    'id' => $val['id'],
                    'from_uid' => $val['from_uid'],
                    'created_at' => $val['created_at'],
                    'talk_type' => $val['talk_type'],
                    'to_uid' => $val['to_uid'],
                ],
                    ReceiveHandleService::TALK_READ, explode(',', $val['msg_ids']));
                ReadOfflineMessage::where(["id"=>$val['id']])->update(['is_pull' => 1]);
                $data[] = json_decode($list,true);
            }

            $lock->release();

            return $this->responseSuccess($data);
        } catch (BadRequestException $e) {
            $lock->release();
            throw new BadRequestHttpException($e->getMessage());
        }
    }

    /*
     * 删除会话
     */
    public function delMeeting(Request $request): JsonResponse
    {
        $request->validate([
            // 对方ID 1-用户ID，2-群ID
            'id'     => 'bail|required|int' ,
            // 1-私聊 2-群聊
            'talk_type' => 'bail|required|string' ,
        ]);

        $msg_id = 0;

        if($request['talk_type'] == Constant::TALK_PRIVATE) {
            TalkSession::Where(function ($query) use ($request) {
                $query->where(['uid' => $request['id'], 'receiver_id' => $request['uid']])->where('talk_type',
                    $request['talk_type']);
            })->orWhere(function ($query) use ($request) {
                $query->where(['uid' => $request['uid'], 'receiver_id' => $request['id']])->where('talk_type',
                    $request['talk_type']);
            })->update(['is_delete' => 1]);

            $my_msg_id = FriendsMessage::where(['from_uid'=>$request['uid'],'to_uid'=>$request['id']])->orderByDesc('id')->value('id');
            $ta_msg_id = FriendsMessage::where(['from_uid'=>$request['id'],'to_uid'=>$request['uid']])->orderByDesc('id')->value('id');

            $msg_id = max($my_msg_id, $ta_msg_id);
        }

        if($request['talk_type'] == Constant::TALK_GROUP) {
            $msg_id = FriendsMessage::where('to_uid',$request['id'])->orderByDesc('id')->value('id');
        }
        TalkSessionRecord::saveData(['from_uid'=>$request['uid'],'to_uid'=>$request['id'],'talk_type'=>$request['talk_type'],'status'=>1,'msg_id'=>$msg_id]);

        return $this->responseSuccess();
    }

    /**
     * desc:删除消息
     * author: mxm
     * Time: 2023/9/12   16:10
     * @param  Request  $request
     * @return JsonResponse
     */
    public function delMsg(Request $request): JsonResponse
    {
        $request->validate([
            'id'     => 'bail|required|int'
        ]);
        $uid= $request['uid'];
        $id= $request['id'];
        TalkSessionDelete::saveData(['id'=>$id,'uid'=>$uid]);
        return $this->responseSuccess();
    }
}