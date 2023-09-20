<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FriendsGroups extends Model
{
    use HasFactory;

    protected $table = 'friends_groups';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'updated_at' => 'datetime:Y-m-d H:i:s' ,
    ];

    /**
     * User: zmm
     * DateTime: 2023/5/30 11:53
     * @param $uid
     * @return array
     */
    public static function getGroupName($uid) : array
    {
        return array_column(self::query()->where('uid' , $uid)->get(['group_name','id'])->toArray() , 'group_name','id');
    }

}
