<?php

namespace App\Http\Controllers\Admin;


use App\GatewayWorker\ReceiveHandleService;
use App\Http\Controllers\Controller;
use App\Jobs\SendMsg;
use App\Models\Friends;
use App\Models\FriendsMessage;
use App\Models\Groups;
use App\Models\GroupsMember;
use App\Models\GroupsNotice;
use App\Models\Members;
use App\Models\MembersToken;
use App\Models\TalkRecordsInvite;
use App\Tool\Constant;
use App\Tool\Utils;
use GatewayClient\Gateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use zjkal\TimeHelper;

class GroupController extends Controller
{
    /**
     * 群组列表
     * User: zmm
     * DateTime: 2023/5/30 11:34
     * @param  Request  $request
     * @return JsonResponse
     */
    public function groups(Request $request): JsonResponse
    {
        $request->validate([
            'id' => 'bail|nullable|int',
            'uid' => 'bail|nullable|int',
            'name' => 'bail|nullable|string',
            'is_dismiss' => 'bail|nullable|in:0,1',
        ]);
        $id = $request['id'];
        $uid = $request['uid'];
        $name = $request['name'];
        $is_dismiss = $request['is_dismiss'] ?? 'false';
        if ($is_dismiss == 0) {
            $is_dismiss = '否';
        }
        $list = Groups::when($uid, function ($query) use ($uid) {
            // 
            $query->where('uid', $uid);
        })->when($id, function ($query) use ($id) {
            // 
            $query->where('id', $id);
        })->when($name, function ($query) use ($name) {
            // 
            $query->where('name', 'like', "%{$name}%");
        })->when($is_dismiss, function ($query) use ($is_dismiss) {
            // 
            if ($is_dismiss == '否') {
                $query->where('is_dismiss', 0);
            } else if ($is_dismiss == 1) {
                $query->where('is_dismiss', $is_dismiss);
            }
        })->orderByDesc('id')->paginate(page_size());
        foreach ($list as $key => $val) {
            $list[$key]['avatar'] = Utils::getAvatarUrl($val['avatar'], $val['driver']);
        }
        return $this->responseSuccess($list);
    }

    /**
     * 群聊详细信息
     * User: zmm
     * DateTime: 2023/6/1 15:47
     * @param  Request  $request
     * @return JsonResponse
     */
    public function groupInfo(Request $request): JsonResponse
    {

        if ('GET' == $request->getMethod()) {
            $request->validate(['id' => 'bail|required|int|min:1']);
            $groupMemberInfo = GroupsMember::query()->where(['group_id' => $request['id'], 'uid' => $request['uid']])->first();
            if (!$groupMemberInfo) {
                throw new BadRequestHttpException(__("你未加入此群聊"));
            }
            // 群信息
            $groupInfo = Groups::query()->where('id', $request['id'])->firstOr([
                'id',
                'name',
                'describe',
                'avatar',
                'max_num',
                'is_mute',
                'is_dismiss',
                'dismissed_at',
                'uid',
                'driver'
            ], function () {
                throw new BadRequestHttpException(__("群聊不存在"));
            });
            $groupInfo['avatar'] = Utils::getAvatarUrl($groupInfo['avatar'], $groupInfo['driver']);
            $groupInfo['is_disturb'] = $groupMemberInfo['is_disturb'];
            $groupInfo['role'] = $groupMemberInfo['role'];            // 群成员
            $groupMember = GroupsMember::query()->from('groups_member', 'g')->join(
                'members as m',
                'g.uid',
                '=',
                'm.id'
            )->where([
                'g.group_id' => $request['id'],
            ])->get([
                'g.notes', 'g.uid', 'm.avatar', 'g.is_mute', 'm.driver', 'g.is_disturb',
                'g.role', 'm.quickblox_id'
            ])->each(function ($v) {
                $v['avatar'] = Utils::getAvatarUrl($v['avatar'], $v['driver']);
            })->toArray();
            // 群成员如果有我的好友 需要把好友备注替换下
            $friendsArr = Friends::query()->where(['uid' => $request['uid']])->whereIn(
                'friend_id',
                array_column($groupMember, 'uid')
            )->pluck('remark', 'friend_id')->toArray();
            foreach ($groupMember as $k => $v) {
                array_key_exists($v['uid'], $friendsArr) && $groupMember[$k]['notes'] = $friendsArr[$v['uid']];
            }
            // 群公告
            $groupNotice = GroupsNotice::getList($request['id'], $groupInfo['uid']);

            return $this->responseSuccess([
                'group_info'   => $groupInfo,
                'group_member' => $groupMember,
                'group_notice' => $groupNotice,
            ]);
        }
        $params = [
            'is_audio' => 'bail|nullable|in:0,1',
            'id'       => 'bail|required|int|min:1',
            'name'     => 'bail|nullable|string|max:30',
            'describe' => 'bail|nullable|string|max:256',
            'avatar'   => 'bail|nullable|string|max:128',
            'is_mute'  => 'bail|nullable|int|in:0,1',
            'driver'   => 'bail|nullable',

        ];
        unset($params['avatar']);
        $request->validate($params);
        $groupInfo = Groups::getGroupInfo($request['id']);
        foreach (array_keys($params) as $v) {
            !is_null($request[$v]) && $groupInfo->setAttribute($v, $request[$v]);
        }
        $groupInfo->save();

        return $this->responseSuccess();
    }

    /**
     * 踢出群聊
     * User: zmm
     * DateTime: 2023/6/5 13:55
     * @param  Request  $request
     * @return JsonResponse
     */
    public function kickOutGroup(Request $request): JsonResponse
    {
        $request->validate(['id' => 'bail|required|int', 'group_id' => 'bail|required|int', 'uid' => 'bail|required|int']);

        $lock = Cache::lock(__FUNCTION__ . $request['uid'], 1);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }
        if ($request['id'] == $request['uid']) {
            throw new BadRequestHttpException(__("不能将自己踢出群聊"));
        }
        $groupMemberModel = GroupsMember::query()->where([
            'uid'      => $request['id'],
            'group_id' => $request['group_id'],
        ])->firstOr(['*'], function () {
            throw new BadRequestHttpException(__("群聊没有此用户"));
        });
        $role = GroupsMember::query()->where(['uid' => $request['uid'], 'group_id' => $request['group_id']])->value('role');
        if ($role == 0) {
            throw new BadRequestHttpException(__("暂无权限"));
        }
        //        $userRole = GroupsMember::query()->where(['uid' => $request['id'],'group_id'=>$request['group_id']])->value('role');
        //        if(isset($userRole) && in_array($userRole,[1,2])){
        //            throw new BadRequestHttpException(__("暂无权限操作该用户"));
        //        }

        DB::beginTransaction();
        $timestamp = TimeHelper::getMilliTimestamp();
        $recordId = FriendsMessage::query()->insertGetId([
            'from_uid'     => $request['uid'],
            'to_uid'       => $request['group_id'],
            'talk_type'    => Constant::TALK_GROUP,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'message'      => '你将%s移出了群聊',
            'timestamp'    => $timestamp,
        ]);

        TalkRecordsInvite::query()->insert([
            'record_id'       => $recordId,
            'type'            => 3, //管理员踢群
            'operate_user_id' => $request['uid'],
            'user_ids'        => $request['id'],
        ]);

        $groupMemberModel->delete();
        DB::commit();

        $message   = "";
        $groupMemberList = GroupsMember::query()->where(['group_id' => $request['group_id']])->whereIn(
            'uid',
            [$request['id']]
        )->pluck('uid', 'id')->toArray();
        $uidArr = array_values($groupMemberList);
        /**
         * 全局变量处理
         */
        $groupGlobalInfo = $tmpArr = Utils::getGlobalDataInstance()->{Utils::getGroupKey($request['group_id'])};
        foreach ($groupMemberList as $v) {
            if (isset($groupGlobalInfo[$v])) {
                unset($groupGlobalInfo[$v]);
            }
        }
        Utils::getGlobalDataInstance()->cas(Utils::getGroupKey($request['group_id']), $tmpArr, $groupGlobalInfo);

        /**
         * 发送消息
         */
        $data     = [
            'id'           => $recordId,
            'from_uid'     => $request['uid'],
            'to_uid'       => intval($request['group_id']),
            'talk_type'    => Constant::TALK_GROUP,
            'is_read'      => 0,
            'is_revoke'    => 0,
            'quote_id'     => 0,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'warn_users'   => "",
            'message'      => $message,
            'created_at'   => time(),
            'timestamp'    => $timestamp,
        ];
        Utils::removeDeviceTag($uidArr, intval($request['group_id']));
        $sendData = ReceiveHandleService::messageStructHandle([$data]);
        $sendData = array_pop($sendData);
        $sendData += ['uuid' => $request['uuid'] ?? null, 'debug' => $request->header('app-encrypt', config('app.debug'))];
        dispatch(new SendMsg($sendData))->onQueue('SendMsg');
        /**
         * 踢出群聊
         */
        foreach (MembersToken::query()->whereIn('uid', [$request['id']])->pluck('client_id')->toArray() as $v) {
            if (!$v) {
                continue;
            }
            Gateway::leaveGroup($v, $request['group_id']);
        }
        return $this->responseSuccess();
    }

    /**
     * 解散群聊
     * User: zmm
     * DateTime: 2023/6/6 10:34
     * @param  Request  $request
     * @return JsonResponse
     */
    public function dismissGroup(Request $request): JsonResponse
    {
        $request->validate(['id' => 'bail|required|int|min:1']);
        $groupModel = Groups::getGroupInfo($request['id']);
        if ($groupModel['uid'] != $request['uid']) {
            throw new BadRequestHttpException(__("暂无权限"));
        }
        DB::beginTransaction();
        $groupModel->setAttribute('is_dismiss', 1);
        $groupModel->setAttribute('dismissed_at', date('Y-m-d H:i:s'));
        $groupModel->save();

        DB::commit();

        return $this->responseSuccess();
    }

    /**
     * 群公告
     * User: zmm
     * DateTime: 2023/6/6 14:33
     * @param  Request  $request
     * @return JsonResponse
     */
    public function noticeGroup(Request $request): JsonResponse
    {
        $method = $request->getMethod();
        if ('POST' == $method) {
            $request->validate([
                'group_id' => 'bail|required|int|min:1',
                'title'    => 'bail|required|string|max:50',
                'content'  => 'bail|required|string|max:300',
                'is_top'   => 'bail|required|int|in:0,1',
            ]);
            Groups::getGroupInfo($request['group_id']);
            GroupsNotice::query()->insert([
                'group_id' => $request['group_id'],
                'uid'      => $request['uid'],
                'title'    => $request['title'],
                'content'  => $request['content'],
                'is_top'   => $request['is_top'],
            ]);
            GroupsNotice::delNotice($request['group_id']);
        } else if ('DELETE' == $method) {
            $request->validate(['id' => 'bail|required|int|min:1']);
            $groupNoticeModel = GroupsNotice::query()->where([
                'uid' => $request['uid'],
                'id'  => $request['id'],
            ])->firstOr(['*'], function () {
                throw new BadRequestHttpException(__("删除失败"));
            });
            $groupNoticeModel->setAttribute('is_delete', 1);
            $groupNoticeModel->save();
            GroupsNotice::delNotice($groupNoticeModel['group_id']);
        } else if ('PUT' == $method) {
            $request->validate([
                'id'       => 'bail|required|int|min:1',
                'uid'      => 'bail|required|int|min:1',
                'group_id' => 'bail|required|int|min:1',
                'title'    => 'bail|required|string|max:50',
                'content'  => 'bail|required|string|max:300',
                'is_top'   => 'bail|required|int|in:0,1',
            ]);
            GroupsNotice::query()->where([
                'uid' => $request['uid'],
                'id'  => $request['id'],
            ])->update([
                'group_id' => $request['group_id'],
                'title'    => $request['title'],
                'content'  => $request['content'],
                'is_top'   => $request['is_top'],
            ]);
            GroupsNotice::delNotice($request['group_id']);
        } else {
            // 
            $request->validate([
                'id'       => 'bail|nullable|int|min:1',
                'uid'      => 'bail|required|int|min:1',
                'group_id' => 'bail|required|int|min:1',
                'title'    => 'bail|nullable|string|max:50',
                'content'  => 'bail|nullable|string|max:300',
                'is_top'   => 'bail|nullable|int|in:0,1',
            ]);

            $list = GroupsNotice::query()->where([
                'uid' => $request['uid'],
                'group_id'  => $request['group_id'],
            ])->when($request['id'], function ($query) use ($request) {
                // 
                $query->where('id', $request['id']);
            })->when($request['title'], function ($query) use ($request) {
                // 
                $query->where('title', 'like', "%{$request['title']}%");
            })->when($request['content'], function ($query) use ($request) {
                // 
                $query->where('content', 'like', "%{$request['content']}%");
            })->when($request['is_top'], function ($query) use ($request) {
                // 
                $query->where('is_top', $request['is_top']);
            })->where('is_delete', 0)->orderByDesc('id')->paginate(page_size());
            return $this->responseSuccess($list);
        }

        return $this->responseSuccess();
    }

    /**
     * 群聊个人禁言
     * User: zmm
     * DateTime: 2023/6/6 16:42
     * @param  Request  $request
     * @return JsonResponse
     */
    public function muteMemberGroup(Request $request): JsonResponse
    {
        $request->validate([
            'group_id' => 'bail|required|int|min:1',
            'uid'      => 'bail|required|int|min:1',
            'is_mute'  => 'bail|required|int|in:0,1',
        ]);
        $role = GroupsMember::query()->where(['uid' => $request['uid'], 'group_id' => $request['group_id']])->value('role');
        if ($role == 0) {
            throw new BadRequestHttpException(__("暂无权限"));
        }
        $groupModel = Groups::getGroupInfo($request['group_id']);
        if (!$groupModel) {
            throw new BadRequestHttpException(__("群组不存在"));
        }
        $groupMemberModel = GroupsMember::query()->where(['group_id' => $groupModel['id'], 'uid' => $request['uid']])->firstOr(['*'], function () {
            throw new BadRequestHttpException(__("群聊没有此会员"));
        });
        //        $userRole = GroupsMember::query()->where(['uid' => $request['uid'],'group_id'=>$request['group_id']])->value('role');
        //        if(isset($userRole) && in_array($userRole,[1,2])){
        //            throw new BadRequestHttpException(__("暂无权限操作该用户"));
        //        }

        $groupMemberModel->setAttribute('is_mute', $request['is_mute']);
        $groupMemberModel->save();

        return $this->responseSuccess($groupMemberModel);
    }

    /**
     * 群组聊天记录
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function messageGroup(Request $request): JsonResponse
    {
        $request->validate([
            'group_id'         => 'bail|required|int',
            'message'         => 'bail|nullable',
        ]);

        $list = FriendsMessage::with(['member' => function ($query) {
            // 
            $query->select(['id', 'nick_name', 'avatar']);
        }])->where([
            'talk_type' => 2,
            'to_uid' => $request['group_id'],
        ])->when($request['message'], function ($query) use ($request) {
            // 
            $query->where('message', 'like', "%{$request['message']}%");
        })->when($request['date'], function ($query) use ($request) {
            // 
            [$startDate, $endDate] = explode(',', $request['date']);
            $query->whereBetween('created_at', [$startDate, $endDate]);
        })->orderByDesc('id')
            ->paginate(page_size(), [
                'id', 'from_uid', 'to_uid', 'is_revoke', 'quote_id', 'is_delete', 'message_type', 'message', 'created_at'
            ]);
        return $this->responseSuccess($list);
    }

    /**
     * desc:群主设置管理员
     * author: mxm
     * Time: 2023/8/31   18:26
     * @param  Request  $request
     * @return JsonResponse
     */
    public function groupManager(Request $request): JsonResponse
    {
        $request->validate(
            [
                'group_id' => 'bail|required|int|min:0',
                'is_manager' => 'bail|required|int|in:0,2',
                'uid' => 'bail|required|string'
            ]
        );

        $info = Groups::where(['id' => $request['group_id'], 'uid' => $request['uid']])->first();
        if ($info) {
            throw new BadRequestHttpException(__("群主不能设置为管理员"));
        }

        $max = Groups::where(['id' => $request['group_id']])->value('max_manager');
        $hasCount = GroupsMember::query()->where([
            'group_id' => $request['group_id'],
            'role'      => '2',
        ])->count();
        $diff = $max - $hasCount;
        if ($diff < 1 && $request['is_manager'] == 2) {
            throw new BadRequestHttpException(__("管理员人数已满"));
        }
        GroupsMember::query()->where([
            'group_id' => $request['group_id'],
        ])->where('uid', $request['uid'])->update(['role' => $request['is_manager']]);

        return $this->responseSuccess();
    }
}
