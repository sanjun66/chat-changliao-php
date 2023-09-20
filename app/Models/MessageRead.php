<?php

namespace App\Models;

use App\Models\Members;
use App\Models\GroupsMember;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MessageRead extends Model
{
    use HasFactory;

    protected $table = 'message_read';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联用户信息啊
     *
     * @return void
     */
    public function readMember()
    {
        return $this->hasOne(Members::class, 'id', 'member_id');
    }

    /**
     * desc: 获取消息已读未读数量
     * author: mxm
     * Time: 2023/8/21   16:02
     */
    public static function getReadNum($message_id,$group_id,$uid){
        $noList = $yesList = [];
        // 获取群成员
        $group_ids = GroupsMember::where('group_id',$group_id)->where('uid','!=',$uid)->pluck('uid')->toArray();
        // 获取该条消息已读的成员
        $read_ids = self::where('message_id',$message_id)->where('member_id','!=',$uid)->pluck('member_id')->toArray();
        $Read_num = count($read_ids);
        $noRead_num = count($group_ids) - $Read_num;
        $wei = array_diff($group_ids,$read_ids);
        if($wei){
            $noList = Members::whereIn('id',$wei)->get('nick_name')->toArray();
            $yesList = Members::whereIn('id',$read_ids)->get('nick_name')->toArray();
        }
        return ['read_num'=>$Read_num,'noRead_num'=>$noRead_num,'noList'=>$noList,'yesList'=>$yesList];
    }

    /**
     * desc:获取该成员有没有读过这条消息
     * author: mxm
     * Time: 2023/8/23   12:10
     */
    public static function isRead($message_id,$uid){
        $id = self::where(['message_id'=>$message_id,'member_id'=>$uid])->value('id');
        return $id > 0 ? '1' : '0';
    }
}
