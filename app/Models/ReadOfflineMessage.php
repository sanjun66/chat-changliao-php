<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReadOfflineMessage extends Model
{

    use HasFactory;
    public $timestamps = false;
    protected $table = 'read_offline_message';
    protected $guarded = ['id'];

//    protected $casts = [
//        'created_at' => 'datetime:Y-m-d H:i:s' ,
//        'updated_at' => 'datetime:Y-m-d H:i:s' ,
//    ];

}