<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class GroupsMember extends Model
{
    use HasFactory;

    protected $table = 'groups_member';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'updated_at' => 'datetime:Y-m-d H:i:s' ,
    ];

    /**
     * desc:获取用户的群ID集合
     * author: mxm
     * Time: 2023/9/4   10:42
     */
    public static function getUserGroup($uid){
        return self::where(['uid'=>$uid])->pluck('group_id')->toArray();
    }
}
