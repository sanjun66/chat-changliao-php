<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;


class GroupsNotice extends Model
{
    use HasFactory;

    protected $table = 'groups_notice';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'updated_at' => 'datetime:Y-m-d H:i:s' ,
    ];

    public static function delNotice($id) : bool
    {
        return Cache::forget(self::getCacheKey($id));
    }

    public static function getCacheKey($id) : string
    {
        return 'group:notice:' . $id;
    }

    public static function getList($id , $uid) : mixed
    {
        return Cache::remember(self::getCacheKey($id) , mt_rand(100 , 200) , function () use ($id , $uid) {
            return self::query()->where([
                'group_id'  => $id ,
                'uid'       => $uid ,
                'is_delete' => 0 ,
            ])->orderByDesc('is_top')->get(['title' , 'content' , 'id'])->toArray();
        });
    }


}
