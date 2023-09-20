<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TalkRecordsForward extends Model
{

    use HasFactory;

    protected $table = 'talk_records_forward';
    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'text'       => 'array' ,
    ];
}
