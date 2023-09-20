<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'setting';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public static function getConfig() : array
    {
        return self::query()->get()->toArray();
    }

    public static function getOssConfigByType($type) : array
    {
        return Setting::whereIn('type',$type)->pluck('value','key')->toArray();
    }
}
