<?php

namespace App\Models;

use App\Tool\Utils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class Groups extends Model
{
    use HasFactory;

    protected $table = 'groups';

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
    public static function getContacts($uid, array $filed = ['*']): array
    {
        $result    = self::query()->when($uid, function ($query) use ($uid) {
            // 
            $query->where('uid', $uid);
        })->orderByDesc('id')->get($filed)->toArray();

        return $result;
    }

    /**
     * 返回群组信息
     * User: zmm
     * DateTime: 2023/5/30 17:38
     * @param  array  $params
     * @param  array  $filed
     * @return array
     */
    public static function getTalkGroupInfo(array $params, array $filed = ['*']): array
    {
        $resultArr = [];
        $result    = $params ? self::query()->whereIn('id', $params)->get($filed)->toArray() : [];
        foreach ($result as $v) {
            $resultArr[$v['id']] = $v;
        }

        return $resultArr;
    }


    public static function getTalkGroupGrep(array $params, $uid): array
    {
        $result    =  self::query()->whereIn('id', $params)->where('is_dismiss','=','0')->pluck('id')->toArray();
        foreach ($result as $key=>$val){
            if(!GroupsMember::query()->where(['group_id'=>$val,'uid'=>$uid])->exists()){
                unset($result[$key]);
            }
        }

        return $result;
    }

    /**
     * 群组信息
     * User: zmm
     * DateTime: 2023/6/1 15:55
     * @param  int  $id
     * @param  array  $filed
     * @return Builder|Model|mixed
     */
    public static function getGroupInfo(int $id, array $filed = ['*']): mixed
    {
        $groupInfo = self::query()->where('id', $id)->firstOr($filed, function () {
            throw new BadRequestHttpException(__("群聊不存在"));
        });
        if ($groupInfo['is_dismiss']) {
            throw new BadRequestHttpException(__("群聊已解散"));
        }

        return $groupInfo;
    }


    /**
     * 返回图片地址
     * User: zmm
     * DateTime: 2023/6/1 12:03
     * @param $avatar
     * @return string
     */
//    public function getAvatarAttribute($avatar): string
//    {
//        return Utils::getAvatarUrl($avatar,$this->driver ?? 0);
//    }
}
