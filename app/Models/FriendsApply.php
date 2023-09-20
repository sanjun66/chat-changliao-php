<?php

namespace App\Models;

use App\Tool\Utils;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FriendsApply extends Model
{
    use HasFactory;

    protected $table = 'friends_apply';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'updated_at' => 'datetime:Y-m-d H:i:s' ,
    ];

    /**
     * 申请列表
     * User: zmm
     * DateTime: 2023/6/14 11:41
     * @param $uid
     * @param  int  $id
     * @return array
     */
    public static function applyList($uid , int $id = 0) : array
    {
        $list = FriendsApply::query()->when($id , function ($query) use ($id) {
            $query->where('id' , $id);
        })->where(['uid' => $uid])->orWhere('friend_id' ,
            $uid)->orderByDesc('id')->get([
            'id' ,
            'friend_id' ,
            'uid' ,
            'state' ,
            'remark' ,
            'created_at' ,
            'process_message' ,
        ])->toArray();
        if ($list) {
            $uidArr    = array_unique(array_merge(array_column($list , 'friend_id') , array_column($list , 'uid')));
            $memberArr = Members::query()->whereIn('id' , $uidArr)->get([
                'avatar' ,
                'id' ,
                'nick_name',
                'driver'
            ])->keyBy('id')->toArray();
            foreach ($memberArr as $k=>$v){
                $memberArr[$k]['avatar'] = Utils::getAvatarUrl($v['avatar'],$v['driver']);
            }
            foreach ($list as $k => $v) {
                // 制造者
                if ($v['uid'] == $uid) {
                    $list[$k]         = array_merge($memberArr[$v['friend_id']] , $v);
                    $list[$k]['flag'] = 'maker';
                    // 接受者
                } else {
                    $list[$k]         = array_merge($memberArr[$v['uid']] , $v);
                    $list[$k]['flag'] = 'taker';
                }
                $list[$k]['created_at'] = strtotime($v['created_at']);
            }
        }
        return $list;
    }

}
