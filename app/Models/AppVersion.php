<?php

namespace App\Models;

use App\Models\Members;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppVersion extends Model
{
    use HasFactory;

    protected $table = 'app_version';

    protected $guarded = ['id'];

    protected $casts = [
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
}
