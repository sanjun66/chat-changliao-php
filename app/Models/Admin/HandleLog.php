<?php


namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class HandleLog extends Model
{
    protected $table = "admin_handle_log";

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'updated_at' => 'datetime:Y-m-d H:i:s' ,
        'content'    => 'array' ,
        'sql'        => 'array' ,
    ];
}