<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FriendsOfflineMessage extends Model
{

    use HasFactory;

    protected $table = 'friends_offline_message';
    protected $guarded = ['id'];

//    protected $casts = [
//        'created_at' => 'datetime:Y-m-d H:i:s' ,
//        'updated_at' => 'datetime:Y-m-d H:i:s' ,
//    ];

}