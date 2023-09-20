<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Admins extends Model
{
    protected $table = "admins";
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'updated_at' => 'datetime:Y-m-d H:i:s' ,
    ];
}