<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TalkSessionRecord extends Model
{

    use HasFactory;

    protected $table = 'talk_session_record';
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
        $model->from_uid = $data['from_uid'];
        $model->to_uid = $data['to_uid'];
        $model->talk_type = $data['talk_type'];
        $model->last_message_id = $data['msg_id'];
        $model->save();
    }
}