<?php

namespace App\Models;

use App\Tool\Utils;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TalkRecordsFile extends Model
{

    use HasFactory;

    protected $table = 'talk_records_file';
    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
    ];
}
