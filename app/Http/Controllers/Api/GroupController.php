<?php

namespace App\Http\Controllers\Api;


use App\GatewayWorker\ReceiveHandleService;
use App\Http\Controllers\Controller;
use App\Jobs\SendMsg;
use App\Models\Friends;
use App\Models\FriendsMessage;
use App\Models\Groups;
use App\Models\GroupsMember;
use App\Models\GroupsNotice;
use App\Models\Members;
use App\Models\MembersInfo;
use App\Models\MembersToken;
use App\Models\TalkRecordsInvite;
use App\Tool\Constant;
use App\Tool\Utils;
use Exception;
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
     * 创建群聊
     * User: zmm
     * DateTime: 2023/6/1 14:31
     * @param  Request  $request
     * @return JsonResponse
     * @throws Exception
     */
    public function createGroup(Request $request): JsonResponse
    {
        $request->validate(['name' => 'bail|nullable|string|max:30', 'member_id' => 'bail|required|string|max:512']);

        // 创建个群
        if (Groups::query()->where('uid', $request['uid'])->count() >= 100) {
            throw new BadRequestHttpException(__("用户最多可以创建100群聊"));
        }
        $memberNameArr = Members::getGroupMembers($request['member_id']);
        if (empty($memberNameArr)) {
            throw new BadRequestHttpException(__("添加的群聊好友不存在"));
        }
        $lock = Cache::lock(__FUNCTION__ . $request['uid'], 1);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }
        $date = date('Y-m-d H:i:s');
        DB::beginTransaction();
        $groupId   = Groups::query()->insertGetId([
            'uid'  => $request['uid'],
            'name' => $request->input(
                'name',
                mb_substr(join('、', array_slice(array_values($memberNameArr), 0, 3)), 0, 30)
            ),
        ]);
        $message   = '';
        $timestamp = TimeHelper::getMilliTimestamp();
        // 群里面发送一条消息
        $recordId = FriendsMessage::query()->insertGetId([
            'from_uid'     => $request['uid'],
            'to_uid'       => $groupId,
            'talk_type'    => Constant::TALK_GROUP,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'message'      => $message,
            'timestamp'    => $timestamp,
        ]);

        // 入群消息来一条
        TalkRecordsInvite::query()->insert([
            'record_id'       => $recordId,
            'type'            => 1,
            'operate_user_id' => $request['uid'],
            'user_ids'        => join(',', array_keys($memberNameArr)),
        ]);
        $data[] = [
            'group_id'   => $groupId,
            'uid'        => $request['uid'],
            'notes'      => $request['user_info']['nick_name'],
            'created_at' => $date,
            'role'       => 1,
        ];
        foreach ($memberNameArr as $k => $v) {
            if ($k == $request['uid']) {
                continue;
            }
            $data[] = ['group_id' => $groupId, 'uid' => $k, 'notes' => $v, 'created_at' => $date ,'role'=> 0];
        }
        GroupsMember::query()->insert($data);
        DB::commit();


        /**
         * 加入全局变量中
         */
        $data = array_map(function ($v) {
            return strtotime($v);
        }, array_column($data, 'created_at', 'uid'));
        Utils::getGlobalDataInstance()->add(Utils::getGroupKey($groupId), $data);

        /**
         * 创建群聊房间
         */
        $uidArr  = array_keys($data);
        foreach (MembersToken::query()->whereIn('uid', $uidArr)->pluck('client_id')->toArray() as $v) {
            if (!$v) {
                continue;
            }
            Gateway::joinGroup($v, $groupId);
        }

        /**
         * 发送群消息
         */
        $data     = [
            'id'           => $recordId,
            'from_uid'     => $request['uid'],
            'to_uid'       => $groupId,
            'talk_type'    => Constant::TALK_GROUP,
            'is_read'      => 0,
            'is_revoke'    => 0,
            'quote_id'     => 0,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'warn_users'   => "",
            'message'      => $message,
            'created_at'   => strtotime($date),
            'timestamp'    => $timestamp,
        ];
        Utils::addDeviceTag($groupId, $uidArr);
        $sendData = ReceiveHandleService::messageStructHandle([$data]);
        $sendData = array_pop($sendData);
        $sendData += ['uuid' => $request['uuid'] ?? null, 'debug' => APP_ENCRYPT];
        dispatch(new SendMsg($sendData))->onQueue('SendMsg');


        return $this->responseSuccess($sendData + ['uuid' => $request['uuid'] ?? null]);
    }

    /**
     * 邀请加入群聊
     * User: zmm
     * DateTime: 2023/6/1 15:02
     * @param  Request  $request
     * @return JsonResponse
     * @throws Exception
     */
    public function inviteGroup(Request $request): JsonResponse
    {
        $request->validate(['id' => 'bail|required|int', 'member_id' => 'bail|required|string|max:512']);

        $groupInfo = Groups::getGroupInfo($request['id']);

        if ($groupInfo['max_num'] <= GroupsMember::query()->where(['group_id' => $groupInfo['id']])->count()) {
            throw new BadRequestHttpException(__("你申请的群聊人已满"));
        }
        // 如果不是群里面的成员不允许拉人
        if (!GroupsMember::query()->where(['uid' => $request['uid'], 'group_id' => $groupInfo['id']])->exists()) {
            throw new BadRequestHttpException(__("暂无权限"));
        }
        // 过滤已经添加过的uid
        $memberNameArr = Members::getGroupMembers($request['member_id']);
        if (empty($memberNameArr)) {
            throw new BadRequestHttpException(__("群聊添加好友不存在"));
        }
        foreach (GroupsMember::query()->where(['group_id' => $request['id']])->whereIn(
            'uid',
            explode(',', $request['member_id'])
        )->pluck('uid')->toArray() as $uid) {
            if (array_key_exists($uid, $memberNameArr)) {
                unset($memberNameArr[$uid]);
            }
        }
        if (empty($memberNameArr)) {
            throw new BadRequestHttpException(__("群聊好友不存在或已添加"));
        }
        $diffCount = $groupInfo['max_num'] - GroupsMember::query()->where(['group_id' => $groupInfo['id']])->count();
        if ($diffCount < count($memberNameArr)) {
            throw new BadRequestHttpException(__("你最多还可以邀请{$diffCount}进入群聊"));
        }
        $date    = date('Y-m-d H:i:s');
        $message = '';
        DB::beginTransaction();
        foreach ($memberNameArr as $k => $v) {
            GroupsMember::query()->updateOrCreate(
                ['group_id' => $request['id'], 'uid' => $k],
                ['group_id' => $request['id'], 'uid' => $k, 'notes' => $v, 'created_at' => $date]
            );
        }
        $timestamp = TimeHelper::getMilliTimestamp();
        // 群里面发送一条消息
        $recordId = FriendsMessage::query()->insertGetId([
            'from_uid'     => $request['uid'],
            'to_uid'       => $request['id'],
            'talk_type'    => Constant::TALK_GROUP,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'message'      => $message,
            'timestamp'    => $timestamp,
        ]);

        // 入群消息来一条
        TalkRecordsInvite::query()->insert([
            'record_id'       => $recordId,
            'type'            => 1,
            'operate_user_id' => $request['uid'],
            'user_ids'        => join(',', array_keys($memberNameArr)),
        ]);

        DB::commit();

        /**
         * 全局变量处理
         */
        $time            = strtotime($date);
        $memberNameArr   = array_map(function ($v) use ($time) {
            return $time;
        }, $memberNameArr);
        $groupGlobalInfo = Utils::getGlobalDataInstance()->{Utils::getGroupKey($request['id'])};
        Utils::getGlobalDataInstance()->cas(
            Utils::getGroupKey($request['id']),
            $groupGlobalInfo,
            ($groupGlobalInfo + $memberNameArr)
        );

        $uidArr = array_keys($memberNameArr);
        /**
         * 加入群聊中
         */
        foreach (MembersToken::query()->whereIn(
            'uid',
            $uidArr
        )->pluck('client_id')->toArray() as $v) {
            if (!$v) {
                continue;
            }
            Gateway::joinGroup($v, $request['id']);
        }
        /**
         * 发送消息
         */
        $data     = [
            'id'           => $recordId,
            'from_uid'     => $request['uid'],
            'to_uid'       => $request['id'],
            'talk_type'    => Constant::TALK_GROUP,
            'is_read'      => 0,
            'is_revoke'    => 0,
            'quote_id'     => 0,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'warn_users'   => "",
            'message'      => $message,
            'created_at'   => strtotime($date),
            'timestamp'    => $timestamp,
        ];
        Utils::addDeviceTag($request['id'], $uidArr);
        $sendData = ReceiveHandleService::messageStructHandle([$data]);
        $sendData = array_pop($sendData);
        $sendData += ['uuid' => $request['uuid'] ?? null, 'debug' => APP_ENCRYPT];
        dispatch(new SendMsg($sendData))->onQueue('SendMsg');



        return $this->responseSuccess($sendData + ['uuid' => $request['uuid'] ?? null]);
    }

    /**
     * desc:扫码进群
     * author: mxm
     * Time: 2023/8/31   15:59
     * @param  Request  $request
     * @return JsonResponse
     * @throws Exception
     */
    public function scanGroup(Request $request): JsonResponse
    {
        $request->validate(['id' => 'bail|required|int', 'invite_id' => 'bail|required|string|max:512']);

        $groupInfo = Groups::getGroupInfo($request['id']);

        if ($groupInfo['max_num'] <= GroupsMember::query()->where(['group_id' => $groupInfo['id']])->count()) {
            throw new BadRequestHttpException(__("你申请的群聊人已满"));
        }
        // 如果不是群里面的成员不允许拉人
        if (!GroupsMember::query()->where(['uid' => $request['invite_id'], 'group_id' => $groupInfo['id']])->exists()) {
            throw new BadRequestHttpException(__("暂无权限"));
        }

        // 过滤已经添加过的uid
        $memberNameArr = GroupsMember::query()->where(['group_id' => $request['id']])->where(
            'uid',$request['uid'])->first();
        if ($memberNameArr) {
            throw new BadRequestHttpException(__("群聊已添加"));
        }

        $diffCount = $groupInfo['max_num'] - GroupsMember::query()->where(['group_id' => $groupInfo['id']])->count();
        if ($diffCount < 1) {
            throw new BadRequestHttpException(__("你最多还可以邀请{$diffCount}进入群聊"));
        }
        $memberInfo = Members::where('id',$request['uid'])->first(['id','nick_name']);
        $date    = date('Y-m-d H:i:s');
        $message = '';
        DB::beginTransaction();

        GroupsMember::query()->updateOrCreate(
            ['group_id' => $request['id'], 'uid' => $request['uid']],
            ['group_id' => $request['id'], 'uid' => $request['uid'], 'notes' => $memberInfo->nick_name, 'created_at' => $date]
        );

        $timestamp = TimeHelper::getMilliTimestamp();
        // 群里面发送一条消息
        $recordId = FriendsMessage::query()->insertGetId([
            'from_uid'     => $request['invite_id'],
            'to_uid'       => $request['id'],
            'talk_type'    => Constant::TALK_GROUP,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'message'      => $message,
            'timestamp'    => $timestamp,
        ]);

        // 入群消息来一条
        TalkRecordsInvite::query()->insert([
            'record_id'       => $recordId,
            'type'            => 1,
            'operate_user_id' => $request['invite_id'],
            'user_ids'        => $request['uid'],
            'join_type'       => '1',
        ]);

        DB::commit();

        /**
         * 全局变量处理
         */
        $time            = strtotime($date);
        $memberNameArr = Members::getGroupMembers($request['uid']);
        $memberNameArr   = array_map(function ($v) use ($time) {
            return $time;
        }, $memberNameArr);
        $groupGlobalInfo = Utils::getGlobalDataInstance()->{Utils::getGroupKey($request['id'])};
        Utils::getGlobalDataInstance()->cas(
            Utils::getGroupKey($request['id']),
            $groupGlobalInfo,
            ($groupGlobalInfo + $memberNameArr)
        );

        $uidArr = array_keys($memberNameArr);
        /**
         * 加入群聊中
         */
        foreach (MembersToken::query()->whereIn(
            'uid',
            $uidArr
        )->pluck('client_id')->toArray() as $v) {
            if (!$v) {
                continue;
            }
            Gateway::joinGroup($v, $request['id']);
        }
        /**
         * 发送消息
         */
        $data     = [
            'id'           => $recordId,
            'from_uid'     => $request['invite_id'],
            'to_uid'       => $request['id'],
            'talk_type'    => Constant::TALK_GROUP,
            'is_read'      => 0,
            'is_revoke'    => 0,
            'quote_id'     => 0,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'warn_users'   => "",
            'message'      => $message,
            'created_at'   => strtotime($date),
            'timestamp'    => $timestamp,
        ];
        Utils::addDeviceTag($request['id'], $uidArr);
        $sendData = ReceiveHandleService::messageStructHandle([$data]);
        $sendData = array_pop($sendData);
        $sendData += ['uuid' => $request['uuid'] ?? null, 'debug' => APP_ENCRYPT];
        dispatch(new SendMsg($sendData))->onQueue('SendMsg');



        return $this->responseSuccess($sendData + ['uuid' => $request['uuid'] ?? null]);
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
        if (in_array($request->getMethod(), ['GET', 'POST'])) {
            $request->validate(['id' => 'bail|required|int|min:1']);
            $groupMemberInfo = GroupsMember::query()->where(['group_id' => $request['id'], 'uid' => $request['uid']])->first();
            if (!$groupMemberInfo) {
                throw new BadRequestHttpException(__("你未加入此群聊"));
            }
            // 群信息
            $groupInfo = Groups::query()->where('id', $request['id'])->firstOr([
                'name',
                'describe',
                'avatar',
                'max_num',
                'max_manager',
                'is_mute',
                'is_dismiss',
                'dismissed_at',
                'is_audio',
                'uid',
                'id',
                'driver',
            ], function () {
                throw new BadRequestHttpException(__("群聊不存在"));
            });
            $groupInfo['avatar'] = Utils::getAvatarUrl($groupInfo['avatar'], $groupInfo['driver']);
            $groupInfo['is_disturb'] = $groupMemberInfo['is_disturb'];
            $groupInfo['role'] = $groupMemberInfo['role'];
            $groupInfo['manager_explain'] = "群管理可以拥有以下能力\n·移除群成员（群主/群管理员除外）\n·可修改群聊名称和群头像\n·可以禁止群成员发言\n·最多存在100个群管理员\n";//群管理员说明
            if ($groupInfo['is_dismiss']) {
                throw new BadRequestHttpException(__("群聊已解散"));
            }
            // 群成员
            $groupMember = GroupsMember::query()->from('groups_member', 'g')->join(
                'members as m',
                'g.uid',
                '=',
                'm.id'
            )->where([
                'g.group_id' => $request['id'],
            ])->get([
                'g.notes',
                'g.uid',
                'm.avatar',
                'g.is_mute',
                'g.is_disturb',
                'g.role',
                'm.quickblox_id',
                'm.driver',
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
            $memberInfo = MembersInfo::query()->firstOrCreate(['uid' => $groupInfo['uid']], ['uid' => $groupInfo['uid']]);
            $groupInfo = array_merge($groupInfo->toArray(), ['audio' => $memberInfo['audio'] ?? 0, 'audio_expire' => date('Y-m-d H:i:s', $memberInfo['audio_expire'] ?? '2999999999')]);
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
            'driver'   => 'bail|nullable|string',
        ];
        $request->validate($params);
        $role = GroupsMember::query()->where(['uid' => $request['uid'],'group_id'=>$request['id']])->value('role');

        $groupInfo = Groups::getGroupInfo($request['id']);
        if (in_array($role,[1,2])) {
            foreach (array_keys($params) as $v) {
                !is_null($request[$v]) && $groupInfo->setAttribute($v, $request[$v]);
            }
            $groupInfo->save();
            return $this->responseSuccess();
        }
        throw new BadRequestHttpException(__("暂无权限操作"));
    }

    /**
     * 踢出群聊
     * User: zmm
     * DateTime: 2023/6/5 13:55
     * @param  Request  $request
     * @return JsonResponse
     * @throws Exception
     */
    public function kickOutGroup(Request $request): JsonResponse
    {
        $request->validate(['id' => 'bail|required|string', 'group_id' => 'bail|required|int']);

        $lock = Cache::lock(__FUNCTION__ . $request['group_id'], 2);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }
        $role = GroupsMember::query()->where(['uid' => $request['uid'],'group_id'=>$request['group_id']])->value('role');
        if($role == 0){
            throw new BadRequestHttpException(__("暂无权限"));
        }
        $idArr = parse_ids($request['id']);
        unset($idArr[$request['id']]);

        foreach($idArr as $k=>$v){
            $userRole = GroupsMember::query()->where(['uid' => $v,'group_id'=>$request['group_id']])->value('role');
            if($role == 2){
                if(isset($userRole) && in_array($userRole,[1,2])){
                    unset($idArr[$k]);//群主和管理员过滤掉
                }
            }else{
                if(isset($userRole) && in_array($userRole,[1])){
                    unset($idArr[$k]);//过滤掉群主
                }
            }
        }


        $groupMemberList = GroupsMember::query()->where(['group_id' => $request['group_id']])->whereIn(
            'uid',
            $idArr
        )->pluck('uid', 'id')->toArray();
        if (!$groupMemberList) {
            throw new BadRequestHttpException(__("参数错误"));
        }
        $uidArr = array_values($groupMemberList);
        DB::beginTransaction();
        $timestamp = TimeHelper::getMilliTimestamp();
        $message   = "";
        $recordId  = FriendsMessage::query()->insertGetId([
            'from_uid'     => $request['uid'],
            'to_uid'       => $request['group_id'],
            'talk_type'    => Constant::TALK_GROUP,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'message'      => $message,
            'timestamp'    => $timestamp,
        ]);

        TalkRecordsInvite::query()->insert([
            'record_id'       => $recordId,
            'type'            => 3,
            'operate_user_id' => $request['uid'],
            'user_ids'        => join(',', $uidArr),
        ]);

        GroupsMember::query()->whereIn('id', array_keys($groupMemberList))->delete();
        DB::commit();

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
        $sendData += ['uuid' => $request['uuid'] ?? null, 'debug' => APP_ENCRYPT];
        dispatch(new SendMsg($sendData))->onQueue('SendMsg');
        /**
         * 踢出群聊
         */
        foreach (MembersToken::query()->whereIn('uid', $idArr)->pluck('client_id')->toArray() as $v) {
            if (!$v) {
                continue;
            }
            Gateway::leaveGroup($v, $request['group_id']);
        }

        return $this->responseSuccess($sendData + ['uuid' => $request['uuid'] ?? null]);
    }

    /**
     * 解散群聊
     * User: zmm
     * DateTime: 2023/6/6 10:34
     * @param  Request  $request
     * @return JsonResponse
     * @throws Exception
     */
    public function dismissGroup(Request $request): JsonResponse
    {
        $request->validate(['group_id' => 'bail|required|int|min:1']);
        $groupModel = Groups::getGroupInfo($request['group_id']);
        if ($groupModel['uid'] != $request['uid']) {
            throw new BadRequestHttpException(__("暂无权限"));
        }
        $message = '';
        DB::beginTransaction();
        $groupModel->setAttribute('is_dismiss', 1);
        $groupModel->setAttribute('dismissed_at', date('Y-m-d H:i:s'));
        $groupModel->save();
        $timestamp = TimeHelper::getMilliTimestamp();
        $recordId  = FriendsMessage::query()->insertGetId([
            'from_uid'     => $request['uid'],
            'to_uid'       => $request['group_id'],
            'talk_type'    => Constant::TALK_GROUP,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'message'      => $message,
            'timestamp'    => $timestamp,
        ]);
        TalkRecordsInvite::query()->insert([
            'record_id'       => $recordId,
            'type'            => 4,
            'operate_user_id' => $request['uid'],
        ]);
        DB::commit();
        /**
         * 清除全局变量
         */
        unset(Utils::getGlobalDataInstance()->{Utils::getGroupKey($request['id'])});
        /**
         * 推送消息
         */
        $data     = [
            'id'           => $recordId,
            'from_uid'     => $request['uid'],
            'to_uid'       => $request['group_id'],
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
        $sendData = ReceiveHandleService::messageStructHandle([$data]);
        $sendData = array_pop($sendData);
        $sendData += ['uuid' => $request['uuid'] ?? null, 'debug' => APP_ENCRYPT];
        dispatch(new SendMsg($sendData))->onQueue('SendMsg');
        /**
         * 解散群
         */
        Gateway::ungroup($request['group_id']);
        Utils::delDeviceTag($request['group_id']);


        return $this->responseSuccess($sendData + ['uuid' => $request['uuid'] ?? null]);
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
        }

        return $this->responseSuccess();
    }

    /**
     * 个人退出群聊
     * User: zmm
     * DateTime: 2023/6/14 14:41
     * @param  Request  $request
     * @return JsonResponse
     * @throws Exception
     */
    public function exitGroup(Request $request): JsonResponse
    {
        $request->validate(['group_id' => 'bail|required|int|min:1']);
        $lock = Cache::lock(__FUNCTION__ . $request['uid'] . $request['group_id'], 2);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }
        if (Groups::query()->where(['uid' => $request['uid'], 'id' => $request['group_id']])->exists()) {
            throw new BadRequestHttpException(__("群主不可以退出群聊"));
        }
        $groupMemberModel = GroupsMember::query()->where([
            'uid'      => $request['uid'],
            'group_id' => $request['group_id'],
        ])->firstOr(['*'], function () {
            throw new BadRequestHttpException(__("不是此群聊的会员"));
        });
        $message          = "";
        DB::beginTransaction();
        $groupMemberModel->delete();
        $timestamp = TimeHelper::getMilliTimestamp();
        $recordId  = FriendsMessage::query()->insertGetId([
            'from_uid'     => $request['uid'],
            'to_uid'       => $request['group_id'],
            'talk_type'    => Constant::TALK_GROUP,
            'message_type' => Constant::GROUP_INVITE_MESSAGE,
            'message'      => $message,
            'timestamp'    => $timestamp,
        ]);
        TalkRecordsInvite::query()->insert([
            'record_id'       => $recordId,
            'type'            => 2,
            'operate_user_id' => $request['uid'],
            'user_ids'        => $request['uid'],
        ]);
        DB::commit();
        /**
         * 清除全局变量
         */
        $groupMemberInfo = Utils::getGlobalDataInstance()->{Utils::getGroupKey($request['id'])};
        if (isset($groupMemberInfo[$request['uid']])) {
            unset($groupMemberInfo[$request['uid']]);
            Utils::getGlobalDataInstance()->cas(
                Utils::getGroupKey($request['id']),
                $groupMemberInfo,
                $groupMemberInfo
            );
        }
        /**
         * 推送消息
         */
        $data     = [
            'id'           => $recordId,
            'from_uid'     => $request['uid'],
            'to_uid'       => $request['group_id'],
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
        Utils::removeDeviceTag($request['uid'], intval($request['group_id']));
        $sendData = ReceiveHandleService::messageStructHandle([$data]);
        $sendData = array_pop($sendData);
        Gateway::sendToGroup(
            $request['group_id'],
            self::cliSuccess($sendData, ReceiveHandleService::TALK_MESSAGE, $request['uuid'] ?? null)
        );
        /**
         * 踢出群聊
         */
        foreach (MembersToken::query()->where('uid', $request['uid'])->pluck('client_id')->toArray() as $v) {
            if (!$v) {
                continue;
            }
            Gateway::leaveGroup($v, $request['group_id']);
        }


        return $this->responseSuccess($sendData + ['uuid' => $request['uuid'] ?? null]);
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
            'id'       => 'bail|required|int|min:1',
            'is_mute'  => 'bail|required|int|in:0,1',
        ]);
        $role = GroupsMember::query()->where(['uid' => $request['uid'],'group_id'=>$request['group_id']])->value('role');
        if($role == 0){
            throw new BadRequestHttpException(__("暂无权限"));
        }

        $userRole = GroupsMember::query()->where(['uid' => $request['id'],'group_id'=>$request['group_id']])->value('role');
        if($role != 1){//不是群主
            if(isset($userRole) && in_array($userRole,[1,2])){
                throw new BadRequestHttpException(__("暂无权限操作该用户"));
            }
        }

        $groupMemberModel = GroupsMember::query()->where([
            'group_id' => $request['group_id'],
            'uid'      => $request['id'],
        ])->firstOr(['*'], function () {
            throw new BadRequestHttpException(__("群聊没有此会员"));
        });
        $groupMemberModel->setAttribute('is_mute', $request['is_mute']);
        $groupMemberModel->save();

        return $this->responseSuccess();
    }

    /**
     * 加入的群列表
     * User: zmm
     * DateTime: 2023/7/14 11:46
     * @param  Request  $request
     * @return JsonResponse
     */
    public function groupList(Request $request): JsonResponse
    {
        $res = Groups::query()->from('groups', 'g')->join(
            'groups_member as m',
            'g.id',
            '=',
            'm.group_id'
        )->where([
            'm.uid'        => $request['uid'],
            'g.is_dismiss' => 0,
        ])->orderByRaw("CONVERT(g.name USING gbk) ASC")->get([
            'g.avatar',
            'g.name',
            'g.id',
            'm.is_disturb',
            'm.role',
            'g.driver'
        ])->toArray();
        foreach ($res as $key => $val) {
            $res[$key]['avatar'] = Utils::getAvatarUrl($val['avatar'], $val['driver']);
        }
        return $this->responseSuccess(['group_list' => $res]);
    }

    /**
     * 群设置免打扰
     * User: zmm
     * DateTime: 2023/7/19 11:47
     * @param  Request  $request
     * @return JsonResponse
     */
    public function groupDisturb(Request $request): JsonResponse
    {
        $request->validate(['group_id' => 'bail|required|int|min:0', 'is_disturb' => 'bail|required|int|in:0,1']);

        GroupsMember::query()->where([
            'group_id' => $request['group_id'],
            'uid'      => $request['uid'],
        ])->update(['is_disturb' => $request['is_disturb']]);
        if($request['is_disturb'] == 1){
            Utils::removeDeviceTag($request['uid'], $request['group_id']);
        }else{
            Utils::addDeviceTag($request['group_id'], [$request['uid']]);
        }
        return $this->responseSuccess();
    }

    /**
     * desc:群设置管理员
     * author: mxm
     * Time: 2023/8/31   14:30
     * @param  Request  $request
     * @return JsonResponse
     */
    public function groupManager(Request $request): JsonResponse
    {
        $request->validate(['group_id' => 'bail|required|int|min:0', 'is_manager' => 'bail|required|int|in:0,2','user_str' => 'bail|required|string']);

        $info = Groups::where(['id'=>$request['group_id'],'uid'=>$request['uid']])->first();
        if(!$info){
            throw new BadRequestHttpException(__("暂无权限"));
        }
        $idArr = explode(',',$request['user_str']);
        $come = count($idArr);
        $hasCount = GroupsMember::query()->where([
            'group_id' => $request['group_id'],
            'role'      => '2',
        ])->count();

        $diffCount = $info->max_manager - $hasCount;//剩余管理员人数
        if($diffCount < $come && $request['is_manager'] == 2){
            throw new BadRequestHttpException(__("最多还可以设置{$diffCount}位管理员"));
        }
        GroupsMember::query()->where([
            'group_id' => $request['group_id'],
        ])->whereIn('uid',$idArr)->update(['role' => $request['is_manager']]);

//        $date    = date('Y-m-d H:i:s');
//        $nameArr = Members::whereIn('id',$idArr)->pluck('nick_name')->toArray();
//        $message = implode(',',$nameArr).' 被添加为管理员';
//        //发送消息
//        $timestamp = TimeHelper::getMilliTimestamp();
//        // 群里面发送一条消息
//        $recordId = FriendsMessage::query()->insertGetId([
//            'from_uid'     => $request['uid'],
//            'to_uid'       => $request['id'],
//            'talk_type'    => Constant::TALK_GROUP,
//            'message_type' => Constant::GROUP_INVITE_MESSAGE,
//            'message'      => $message,
//            'timestamp'    => $timestamp,
//        ]);
//
//        // 入群消息来一条
//        TalkRecordsInvite::query()->insert([
//            'record_id'       => $recordId,
//            'type'            => 5,
//            'operate_user_id' => $request['uid'],
//            'user_ids'        => join(',', $idArr),
//        ]);
//
//        $data     = [
//            'id'           => $recordId,
//            'from_uid'     => $request['uid'],
//            'to_uid'       => $request['id'],
//            'talk_type'    => Constant::TALK_GROUP,
//            'is_read'      => 0,
//            'is_revoke'    => 0,
//            'quote_id'     => 0,
//            'message_type' => Constant::GROUP_INVITE_MESSAGE,
//            'warn_users'   => "",
//            'message'      => $message,
//            'created_at'   => strtotime($date),
//            'timestamp'    => $timestamp,
//        ];
//        $sendData = ReceiveHandleService::messageStructHandle([$data]);
//        $sendData = array_pop($sendData);
//        $sendData += ['uuid' => $request['uuid'] ?? null, 'debug' => APP_ENCRYPT];
//        dispatch(new SendMsg($sendData))->onQueue('SendMsg');
//        return $this->responseSuccess($sendData + ['uuid' => $request['uuid'] ?? null]);
        return $this->responseSuccess();
    }
}
