<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class RolesAuthorities extends Model
{
    protected $table = "roles_authorities";
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'updated_at' => 'datetime:Y-m-d H:i:s' ,
    ];


}