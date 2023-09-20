<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TalkSessionDelete extends Model
{

    use HasFactory;

    protected $table = 'talk_session_delete';
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
        $model->uid = $data['uid'];
        $model->msg_id = $data['id'];
        $model->save();
    }
}