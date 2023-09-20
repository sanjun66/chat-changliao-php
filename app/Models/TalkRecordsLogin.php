<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TalkRecordsLogin extends Model
{

    use HasFactory;

    protected $table = 'talk_records_login';

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
    ];
}
