<?php

namespace App\Models;

use App\Tool\Constant;
use App\Tool\Utils;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Friends extends Model
{
    use HasFactory;

    protected $table = 'friends';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 返回好友列表
     * User: zmm
     * DateTime: 2023/5/30 11:56
     * @param $uid
     * @return array
     */
    public static function getContacts($uid): array
    {
        $result    = [];
        $list      = self::query()->from('friends', 'f')->join(
            'members as m',
            'f.friend_id',
            '=',
            'm.id'
        )->where(['f.uid' => $uid])->orderByRaw("CONVERT(f.remark USING gbk ) ASC")->get([
            'f.friend_id',
            'f.group_id',
            'f.remark',
            'f.is_black',
            'f.is_disturb',
            'm.state',
            'm.avatar',
            'm.sign',
            'm.account',
            'm.quickblox_id',
            'm.driver'
        ])->each(function ($v) {
            $v['avatar'] = Utils::getAvatarUrl($v['avatar'],$v['driver']);
        })->toArray();
        $groupName = array_merge([0 => '我的好友'], FriendsGroups::getGroupName($uid));
        $tmpArr    = [];
        foreach ($list as $value) {
            $tmpArr[$value['group_id']][] = $value;
        }
        foreach ($tmpArr as $key => $value) {
            $result[] = [
                'group_name' => $groupName[$key],
                'group_list' => $value,
            ];
        }
        count($result) >= 2 && array_unshift($result, array_pop($result));

        return $result ?: [['group_name' => '我的好友', 'group_list' => []]];
    }

    /**
     * 判断是否为好友或者群聊会员
     * User: zmm
     * DateTime: 2023/6/8 17:45
     * @param $fromUid
     * @param $toUid
     * @param  int  $talkType
     */
    public static function isFriendOrGroupMember($fromUid , $toUid , int $talkType = Constant::TALK_PRIVATE)
    {
        if ($talkType == Constant::TALK_PRIVATE) {
            // from把to 拉黑了 from可以发消息 to不可以发消息
            if (!self::query()->where(['uid' => $fromUid , 'friend_id' => $toUid])->exists()) {
                throw new BadRequestHttpException("不是好友无法聊天");
            }
            // 不是好友
            $result = self::query()->where(['uid' => $toUid , 'friend_id' => $fromUid])->value('is_black');
            if (is_null($result)) {
                throw new BadRequestHttpException("你还不是他（她）朋友，请先加好友",null,Constant::FRIEND_DELETE);
            }
            if ($result) {
                throw new BadRequestHttpException("对方把您加入黑名单了");
            }
        } else {
            $role = GroupsMember::query()->where(['uid' => $fromUid,'group_id'=>$toUid])->value('role');

            $res = Groups::query()->from('groups' , 'g')->join('groups_member as m' , 'g.id' , '=' ,
                'm.group_id')->where(['m.uid' => $fromUid , 'm.group_id' => $toUid])->firstOr(['m.is_mute' , 'g.uid','g.is_dismiss','g.is_mute as g_mute'] ,
                function () {
                    throw new BadRequestHttpException("群聊不存在");
                });
            if ($res['uid'] != $fromUid && $res['g_mute'] && $role == 0) {
                throw new BadRequestHttpException("群聊已被禁言");
            }
            if ($res['is_mute'] && $role == 0) {
                throw new BadRequestHttpException("你已被禁言");
            }
            if ($res['is_dismiss']) {
                throw new BadRequestHttpException("群聊已解散");
            }
        }
    }

    /**
     * desc:获取好友或者群组之间为免打扰的集合ID
     * author: mxm
     * Time: 2023/8/28   17:24
     */
    public static function checkIsDisturb($from_uid,$to_uid,$talk_type){
        if($talk_type == Constant::TALK_PRIVATE){
            return Friends::where(['friend_id'=>$from_uid,'uid'=>$to_uid,'is_disturb'=>1])->pluck('uid')->toArray();
        }
        if($talk_type == Constant::TALK_GROUP){
            //查看群里现在有多少人是免打扰
            return Friends::where(['group_id'=>$to_uid,'is_disturb'=>1])->where('uid','!=',$from_uid)->pluck('uid')->toArray();
        }
    }

    /**
     * desc:
     * author: mxm
     * Time: 2023/9/5   16:03
     * @param $from_uid
     * @param $to_uid
     * @param $talk_type
     */
    public static function getIsDisturb($from_uid,$to_uid,$talk_type){
        Log::info("记录",['friend_id'=>$to_uid,'uid'=>$from_uid]);
        if($talk_type == Constant::TALK_PRIVATE){
            return Friends::where(['friend_id'=>$to_uid,'uid'=>$from_uid])->value('is_disturb');
        }
        if($talk_type == Constant::TALK_GROUP){
            return GroupsMember::where(['group_id'=>$to_uid,'uid'=>$from_uid])->value('is_disturb');
        }
    }
}
