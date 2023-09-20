<?php


namespace App\Http\Controllers\Api;

use App\GatewayWorker\ReceiveHandleService;
use App\Http\Controllers\Controller;
use App\Jobs\SendMsg;
use App\Models\Friends;
use App\Models\FriendsApply;
use App\Models\FriendsGroups;
use App\Models\FriendsMessage;
use App\Models\Groups;
use App\Models\Members;
use App\Models\TalkRecordsFriend;
use App\Models\TalkSession;
use App\Models\TalkSessionRecord;
use App\Tool\Constant;
use App\Tool\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use zjkal\TimeHelper;

class FriendController extends Controller
{
    /**
     * 好友列表
     * User: zmm
     * DateTime: 2023/5/30 11:34
     * @param  Request  $request
     * @return JsonResponse
     */
    public function friends(Request $request) : JsonResponse
    {
        return $this->responseSuccess(['friend_list' => Friends::getContacts($request['uid'])]);
    }


    /**
     * 删除好友
     * User: zmm
     * DateTime: 2023/6/1 18:12
     */
    public function delFriends(Request $request) : JsonResponse
    {
        $request->validate(['friend_id' => 'bail|required|int|min:1']);

        $friendModel = Friends::query()->where([
            'uid'       => $request['uid'] ,
            'friend_id' => $request['friend_id'] ,
        ])->firstOr(['*'] , function () {
            throw new BadRequestHttpException(__("删除的好友不存在"));
        });

        DB::beginTransaction();
        $friendModel->delete();
        TalkSession::query()->where([
            'uid'         => $request['uid'] ,
            'talk_type'   => Constant::TALK_PRIVATE ,
            'receiver_id' => $request['id'] ,
        ])->delete();

        $my_msg_id = FriendsMessage::where(['from_uid'=>$request['uid'],'to_uid'=>$request['friend_id']])->orderByDesc('id')->value('id');
        $ta_msg_id = FriendsMessage::where(['from_uid'=>$request['friend_id'],'to_uid'=>$request['uid']])->orderByDesc('id')->value('id');
        $msg_id = max($my_msg_id, $ta_msg_id);
        TalkSessionRecord::saveData(['from_uid'=>$request['uid'],'to_uid'=>$request['friend_id'],'talk_type'=> Constant::TALK_PRIVATE,'msg_id'=>$msg_id]);

        DB::commit();

        return $this->responseSuccess();
    }

    /**
     * 安卓删除好友
     * User: zmm
     * DateTime: 2023/6/1 18:12
     */
    public function delAndroidFriends(Request $request) : JsonResponse
    {
        $request->validate(['friend_id' => 'bail|required|int|min:1']);

        $friendModel = Friends::query()->where([
            'uid'       => $request['uid'] ,
            'friend_id' => $request['friend_id'] ,
        ])->firstOr(['*'] , function () {
            throw new BadRequestHttpException(__("删除的好友不存在"));
        });

        DB::beginTransaction();
        $friendModel->delete();
        TalkSession::query()->where([
            'uid'         => $request['uid'] ,
            'talk_type'   => Constant::TALK_PRIVATE ,
            'receiver_id' => $request['id'] ,
        ])->delete();

        $my_msg_id = FriendsMessage::where(['from_uid'=>$request['uid'],'to_uid'=>$request['friend_id']])->orderByDesc('id')->value('id');
        $ta_msg_id = FriendsMessage::where(['from_uid'=>$request['friend_id'],'to_uid'=>$request['uid']])->orderByDesc('id')->value('id');
        $msg_id = max($my_msg_id, $ta_msg_id);
        TalkSessionRecord::saveData(['from_uid'=>$request['uid'],'to_uid'=>$request['friend_id'],'talk_type'=>Constant::TALK_PRIVATE,'msg_id'=>$msg_id]);

        DB::commit();

        return $this->responseSuccess();
    }


    /**
     * 搜索好友
     * User: zmm
     * DateTime: 2023/5/30 18:32
     * @param  Request  $request
     * @return JsonResponse
     */
    public function searchFriends(Request $request) : JsonResponse
    {
        $request->validate(['keywords' => 'bail|required|max:128']);
        $memberList = Members::query()->unionAll(Members::query()->where('phone' , $request['keywords'])->select([
            'nick_name' ,
            'sign' ,
            'avatar' ,
            'id' ,
            'phone' ,
            'driver'
        ]))->unionAll(Members::query()->where('account' , $request['keywords'])->select([
            'nick_name' ,
            'sign' ,
            'avatar' ,
            'id' ,
            'account' ,
            'driver'
        ]))->where('email' , $request['keywords'])->get([
            'nick_name' ,
            'sign' ,
            'avatar' ,
            'id' ,
            'email as keywords' ,
            'driver'
        ])->keyBy('id')->toArray();
        $memberList = array_values(array_unique($memberList));
        // 查看好友列表是否已成为自己的好友
        foreach ($memberList as $k => $v) {
            if ($request['uid'] == $v['id']) {
                $memberList[$k]['is_friend'] = true;
            } else {
                $memberList[$k]['is_friend'] = Friends::query()->where([
                    'uid'       => $request['uid'] ,
                    'friend_id' => $v['id'] ,
                ])->exists();
            }
            $memberList[$k]['avatar'] = Utils::getAvatarUrl($v['avatar'],$v['driver']);

        }

        return $this->responseSuccess(['friend_list' => $memberList]);
    }

    /**
     * 好友申请
     * User: zmm
     * DateTime: 2023/5/30 18:32
     * @param  Request  $request
     * @return JsonResponse
     */
    public function friendsApply(Request $request) : JsonResponse
    {
        $request->validate([
            // id
            'id'     => 'bail|required|int' ,
            // 申请描述
            'remark' => 'bail|nullable|string|max:50' ,
            // 备注
            'notes'  => 'bail|nullable|string|max:20' ,
        ]);
        if ($request['uid'] == $request['id']) {
            throw new BadRequestHttpException(__("已经是好友了"));
        }
        $memberModel = Members::query()->where('id' , $request['id'])->firstOr(['*'] , function () {
            throw new BadRequestHttpException(__("你添加的好友不存在"));
        });
        if (Friends::query()->where(['uid' => $request['uid'] , 'friend_id' => $request['id']])->exists()) {
            throw new BadRequestHttpException(__("你们已经成为好友了"));
        }
        if (FriendsApply::query()->where([
            'uid'       => $request['uid'] ,
            'friend_id' => $memberModel['id'] ,
            'state'     => 1 ,
        ])->exists()) {
            throw new BadRequestHttpException(__("等待审核中"));
        }
        $uuid = $request['uuid'] ?? null;
        $date = date('Y-m-d H:i:s');
        // 需要审核
        $applyData = [
            'uid'             => $request['uid'] ,
            'friend_id'       => $memberModel['id'] ,
            'created_at'      => $date ,
            'state'           => 1 ,
            'remark'          => strval($request['remark'] ?? '') ,
            'process_message' => strval($request['notes'] ?? '') ,
        ];
        DB::beginTransaction();
        if (!$memberModel['apply_auth']) {
            $applyData['state'] = 2;
            foreach ([
                [
                    'uid'        => $request['uid'] ,
                    'friend_id'  => $request['id'] ,
                    'remark'     => $memberModel['nick_name'] ,
                    'created_at' => $date ,
                ] ,
                [
                    'uid'        => $request['id'] ,
                    'friend_id'  => $request['uid'] ,
                    'remark'     => strval($request->input('notes' , $request['user_info']['nick_name'])) ,
                    'created_at' => $date ,
                ] ,
            ] as $v
            ) {
                Friends::query()->firstOrCreate(['uid' => $v['uid'] , 'friend_id' => $v['friend_id']] , $v);
            }
            // 发送消息
            $data       = [
                'from_uid'     => intval($request['uid']) ,
                'to_uid'       => intval($request['id']) ,
                'talk_type'    => Constant::TALK_PRIVATE ,
                'quote_id'     => 0 ,
                'message'      => '' ,
                'message_type' => Constant::FRIEND_MESSAGE ,
                'warn_users'   => '' ,
                'created_at'   => date('Y-m-d H:i:s') ,
                'timestamp'    => TimeHelper::getMilliTimestamp() ,
                'pwd'          => '' ,
            ];
            $data['id'] = FriendsMessage::query()->insertGetId($data);
            TalkRecordsFriend::query()->insert([
                'process_message' => strval($request['remark'] ?? '') ,
                'record_id'       => $data['id'] ,
                'state'           => $applyData['state'] ,
            ]);
            DB::commit();
            $sendData = ReceiveHandleService::messageStructHandle([$data]);
            $sendData = array_pop($sendData);
            $sendData += ['uuid' => $uuid , 'debug' => APP_ENCRYPT];
            dispatch(new SendMsg($sendData))->onQueue('SendMsg');

            return $this->responseSuccess($sendData);
        }
        $params       = [
            'from_uid'     => intval($request['uid']) ,
            'to_uid'       => intval($request['id']) ,
            'talk_type'    => Constant::TALK_PRIVATE ,
            'pwd'          => '' ,
            'is_read'      => 0 ,
            'is_revoke'    => 0 ,
            'quote_id'     => 0 ,
            'warn_users'   => '' ,
            'message'      => '' ,
            'message_type' => Constant::FRIEND_APPLY_MESSAGE ,
            'timestamp'    => TimeHelper::getMilliTimestamp() ,
            'created_at'   => $date ,
        ];
        $params['id'] = FriendsMessage::query()->insertGetId($params);
        FriendsApply::query()->insert($applyData + ['record_id' => $params['id']]);
        DB::commit();
        // 记录消息
        $sendData = ReceiveHandleService::messageStructHandle([$params]);
        $sendData = array_pop($sendData);
        $sendData += ['uuid' => $uuid , 'debug' => APP_ENCRYPT];
        dispatch(new SendMsg($sendData))->onQueue('SendMsg');
        unset($sendData['debug']);

        return $this->responseSuccess($sendData);
    }

    /**
     * 申请列表
     * User: zmm
     * DateTime: 2023/5/30 18:33
     * @param  Request  $request
     * @return JsonResponse
     */
    public function applyList(Request $request) : JsonResponse
    {
        $list = FriendsApply::applyList($request['uid']);

        return $this->responseSuccess(['apply_list' => $list]);
    }


    /**
     * 审核好友申请
     * User: zmm
     * DateTime: 2023/5/30 18:33
     * @param  Request  $request
     * @return JsonResponse
     */
    public function checkApply(Request $request) : JsonResponse
    {
        $request->validate([
            // 申请列表id
            'id'              => 'bail|required|int' ,
            // 2 同意 3拒绝
            'state'           => 'bail|nullable|int|in:2,3' ,
            // 拒绝原因
            'process_message' => "bail|nullable|string|max:50" ,
        ]);
        $applyInfo = FriendsApply::query()->where('id' , $request['id'])->firstOr(['*'] , function () {
            throw new BadRequestHttpException(__("参数错误"));
        });
        if ($applyInfo['friend_id'] != $request['uid']) {
            throw new BadRequestHttpException(__("等待好友审核"));
        }
        if (in_array($applyInfo['state'] , [2 , 3])) {
            throw new BadRequestHttpException(__("用户已添加或者已拒绝"));
        }
        DB::beginTransaction();
        if ($request['state'] == 2) {
            foreach ([
                [
                    'uid'       => $applyInfo['uid'] ,
                    'friend_id' => $applyInfo['friend_id'] ,
                    'remark'    => $request['user_info']['nick_name'] ,
                ] ,
                [
                    'uid'       => $applyInfo['friend_id'] ,
                    'friend_id' => $applyInfo['uid'] ,
                    'remark'    => Members::query()->where('id' , $applyInfo['uid'])->value('nick_name') ,
                ] ,
            ] as $value
            ) {
                Friends::query()->firstOrCreate(['uid' => $value['uid'] , 'friend_id' => $value['friend_id']] , $value);
            }
        } else {
            $request['process_message'] && $applyInfo->setAttribute('process_message' , $request['process_message']);
        }
        $applyInfo->setAttribute('state' , $request['state']);
        $applyInfo->save();
        DB::commit();
        // 发送消息
        $data = [
            'from_uid'     => $request['uid'] ,
            'to_uid'       => $applyInfo['uid'] ,
            'talk_type'    => Constant::TALK_PRIVATE ,
            'quote_id'     => 0 ,
            'message'      => '' ,
            'message_type' => Constant::FRIEND_MESSAGE ,
            'warn_users'   => '' ,
            'created_at'   => date('Y-m-d H:i:s') ,
            'timestamp'    => TimeHelper::getMilliTimestamp() ,
            'pwd'          => '' ,
        ];
        DB::beginTransaction();
        $data['id'] = FriendsMessage::query()->insertGetId($data);
        TalkRecordsFriend::query()->insert([
            'process_message' => strval($request['process_message'] ?? '') ,
            'record_id'       => $data['id'] ,
            'state'           => $request['state'] ,
        ]);
        DB::commit();
        $sendData = ReceiveHandleService::messageStructHandle([$data]);
        $sendData = array_pop($sendData);
        $sendData += ['uuid' => $request['uuid'] ?? null , 'debug' => APP_ENCRYPT];
        dispatch(new SendMsg($sendData))->onQueue('SendMsg');


        return $this->responseSuccess($sendData);
    }

    /**
     * 创建分组 修改分组 删除分组
     * User: zmm
     * DateTime: 2023/5/30 18:33
     * @param  Request  $request
     * @return JsonResponse
     */
    public function friendGroup(Request $request) : JsonResponse
    {
        $method = $request->getMethod();
        if ('POST' == $method) {
            $request->validate(['group_name' => 'bail|required|string|max:50']);
            FriendsGroups::query()->insert(['uid' => $request['uid'] , 'group_name' => $request['group_name']]);
        } else if ('DELETE' == $method) {
            $request->validate(['group_id' => 'bail|required|int|min:1']);
            if (Friends::query()->where(['uid' => $request['uid'] , 'group_id' => $request['group_id']])->exists()) {
                throw new BadRequestHttpException(__("不能删除该分组"));
            }
            FriendsGroups::query()->where(['uid' => $request['uid'] , 'id' => $request['group_id']])->delete();
        } else if ('PUT' == $method) {
            $request->validate([
                'group_name' => 'bail|required|string|max:50' ,
                'group_id'   => 'bail|required|int|min:1' ,
            ]);
            FriendsGroups::query()->where([
                'uid' => $request['uid'] ,
                'id'  => $request['group_id'] ,
            ])->update(['group_name' => $request['group_name']]);
        }

        return $this->responseSuccess();
    }

    /**
     * 移动到分组
     * User: zmm
     * DateTime: 2023/6/6 11:53
     * @param  Request  $request
     * @return JsonResponse
     */
    public function moveFriendGroup(Request $request) : JsonResponse
    {
        $request->validate(['group_id' => 'bail|required|int|min:0' , 'ids' => 'bail|required|string|max:256']);
        if ($request['group_id'] && !Groups::query()->where([
                'id'  => $request['group_id'] ,
                'uid' => $request['uid'] ,
            ])->exists()) {
            throw new BadRequestHttpException(__("分组不存在"));
        }
        Friends::query()->where('uid' , $request['uid'])->whereIn('friend_id' ,
            parse_ids($request['ids']))->update(['group_id' => $request['group_id']]);

        return $this->responseSuccess();

    }

    /**
     * 好友黑名单
     * User: zmm
     * DateTime: 2023/6/6 11:53
     * @param  Request  $request
     * @return JsonResponse
     */
    public function friendBlack(Request $request) : JsonResponse
    {
        $request->validate(['friend_id' => 'bail|nullable|int|min:0']);
        $method = $request->getMethod();
        //  黑名单列表
        if ('GET' == $method) {
            $list = Friends::query()->from('friends' , 'f')->join(
                'members as m' ,
                'f.friend_id' ,
                '=' ,
                'm.id'
            )->where([
                'f.uid'      => $request['uid'] ,
                'f.is_black' => 1 ,
            ])->orderByRaw("CONVERT(f.remark USING gbk ) ASC")->get([
                'f.remark' ,
                'm.avatar' ,
                'f.friend_id' ,
            ])->each(function ($v) {
                $v['avatar'] = Utils::getAvatarUrl($v['avatar']);
            })->toArray();

            return $this->responseSuccess(['friend_black' => $list]);
        } else if ('POST' == $method) {
            $request['friend_id'] && Friends::query()->where([
                'uid'       => $request['uid'] ,
                'friend_id' => $request['friend_id'] ,
            ])->update(['is_black' => 1]);
        } else if ('PUT' == $method) {
            $request['friend_id'] && Friends::query()->where([
                'uid'       => $request['uid'] ,
                'friend_id' => $request['friend_id'] ,
            ])->update(['is_black' => 0]);
        }

        return $this->responseSuccess();
    }

    /**
     * 黑名单列表
     * User: zmm
     * DateTime: 2023/8/8 15:41
     * @param  Request  $request
     * @return JsonResponse
     */
    public function friendBlackList(Request $request) : JsonResponse
    {
        $list = Friends::query()->from('friends' , 'f')->join(
            'members as m' ,
            'f.friend_id' ,
            '=' ,
            'm.id'
        )->where([
            'f.uid'      => $request['uid'] ,
            'f.is_black' => 1 ,
        ])->orderByRaw("CONVERT(f.remark USING gbk ) ASC")->get([
            'f.remark' ,
            'm.avatar' ,
            'f.friend_id' ,
        ])->each(function ($v) {
            $v['avatar'] = Utils::getAvatarUrl($v['avatar']);
        })->toArray();

        return $this->responseSuccess(['friend_black' => $list]);
    }
    /**
     * 好友备注
     * User: zmm
     * DateTime: 2023/6/6 11:53
     * @param  Request  $request
     * @return JsonResponse
     */
    public function friendNotes(Request $request) : JsonResponse
    {
        $request->validate(['friend_id' => 'bail|required|int|min:0' , 'remark' => 'bail|nullable|string|max:20']);
        $remark = $request['remark'] ?: Members::getCacheInfo($request['friend_id'])['nick_name'];
        Friends::query()->where('uid' , $request['uid'])->where('friend_id' ,
            $request['friend_id'])->update(['remark' => $remark]);

        return $this->responseSuccess();
    }

    /**
     * 好友免打扰
     * User: zmm
     * DateTime: 2023/7/19 11:44
     * @param  Request  $request
     * @return JsonResponse
     */
    public function friendDisturb(Request $request) : JsonResponse
    {
        $request->validate(['friend_id' => 'bail|required|int|min:0' , 'is_disturb' => 'bail|required|int|in:0,1']);

        Friends::query()->where([
            'uid'       => $request['uid'] ,
            'friend_id' => $request['friend_id'] ,
        ])->update(['is_disturb' => $request['is_disturb']]);

        return $this->responseSuccess();
    }
}
