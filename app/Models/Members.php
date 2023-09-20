<?php

namespace App\Models;

use App\Tool\Utils;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

class Members extends Model
{
    use HasFactory;

    protected $table = 'members';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'updated_at' => 'datetime:Y-m-d H:i:s' ,
    ];

    /**
     * 获取用户缓存数据
     * User: zmm
     * DateTime: 2023/6/1 12:16
     * @param $uid
     * @param  bool  $force
     * @return mixed
     */
    public static function getCacheInfo($uid , bool $force = false) : mixed
    {
        $uidArr = is_array($uid) ? $uid : [$uid];
        rsort($uidArr);
        $uidArr = array_unique($uidArr);
        $key    = [];
        foreach ($uidArr as $v) {
            $key[] = 'member:' . $v;
        }
        Redis::select(1);
        if ($force) {
            START:
            $result = Members::query()->whereIn('id' , $uidArr)->orderByDesc('id')->get()->keyBy('id')->toArray();
            foreach ($result as $k => $v){
                $result[$k]['avatar'] = Utils::getAvatarUrl($v['avatar'],$v['driver']);
            }
            $res    = array_combine($uidArr , $result);
            if ($result) {
                array_walk($result , function (&$val) {
                    $val = json_encode($val , JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                });
                Redis::mset(array_combine($key , $result));
                Redis::select(0);

                return is_array($uid) ? $res : ($res[$uid] ?? []);

            }
            Redis::select(0);

            return [];
        }

        $res = Redis::mget($key);
        array_walk($res , function (&$val) {
            $val = $val ? json_decode($val , true) : [];
        });
        if (count(array_filter($res)) != count($res)) {
            goto START;
        }
        $res = array_combine($uidArr , $res);
        Redis::select(0);

        return is_array($uid) ? $res : ($res[$uid] ?? []);

    }

    /**
     * 返回好友信息
     * User: zmm
     * DateTime: 2023/5/30 17:40
     * @param  array  $params
     * @param  array  $filed
     * @return array
     */
    public static function getInfo(array $params , array $filed = ['*']) : array
    {
        $resultArr = [];
        $result    = self::query()->whereIn('id' , $params)->get($filed)->toArray();
        foreach ($result as $v) {
            $resultArr[$v['id']] = $v;
        }

        return $resultArr;
    }

    /**
     * 返回图片地址
     * User: zmm
     * DateTime: 2023/6/1 12:03
     * @param $avatar
     * @return string
     */
//    public function getAvatarAttribute($avatar) : string
//    {
//        return Utils::getAvatarUrl($avatar);
//    }

    /**
     * 返回用户名与uid
     * User: zmm
     * DateTime: 2023/6/1 15:10
     * @param $uidStr
     * @return array
     */
    public static function getGroupMembers($uidStr) : array
    {
        return Members::query()->whereIn('id' , array_unique(explode(',' ,
            $uidStr)))->orderByRaw("CONVERT(nick_name USING gbk ) ASC")->pluck('nick_name' ,
            'id')->toArray();
    }
}
