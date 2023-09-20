<?php

namespace App\Models;

use App\Models\Members;
use App\Models\GroupsMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageRepeater extends Model
{
    use HasFactory;

    protected $table = 'message_repeater';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * desc:获取该成员有没有读过这条消息
     * author: mxm
     * Time: 2023/8/23   12:10
     */
    public static function isRead($message_id,$uid){
        $id = self::where(['message_id'=>$message_id,'member_id'=>$uid])->value('id');
        return $id > 0 ? '1' : '0';
    }

    /**
     * desc:插入数据
     * Time: 2023/8/29   15:20
     */
    public static function saveData($data){
        $model = new self;
        $model->msg_id = $data['id'];
        $model->recipient_id = $data['to_uid'];
        $model->talk_type = $data['talk_type'];
        $model->save();
    }


}
