<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class Authorities extends Model
{
    protected $table = "authorities";
    protected $guarded = ['id'];
    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'updated_at' => 'datetime:Y-m-d H:i:s' ,
    ];

    public static function authList($is_show = null)
    {
        $array = self::query()->orderByDesc('sort')->when(!is_null($is_show) , function ($query) use ($is_show) {
            $query->where('is_show' , $is_show);
        })->get()->toArray();

        return auth_list($array);
    }

}