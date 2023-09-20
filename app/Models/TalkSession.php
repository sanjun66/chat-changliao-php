<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TalkSession extends Model
{

    use HasFactory;

    protected $table = 'talk_session';
    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s' ,
        'updated_at' => 'datetime:Y-m-d H:i:s' ,
    ];

    /**
     * desc:插入数据
     * Time: 2023/8/29   15:20
     */
    public static function saveData($data){
        $model = new self;
        $model->talk_type = $data['talk_type'];
        $model->uid = $data['from_uid'];
        $model->receiver_id = $data['to_uid'];
        $model->msg_id = $data['id'];
        $model->save();
    }
}