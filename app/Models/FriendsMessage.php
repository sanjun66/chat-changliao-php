<?php

namespace App\Models;

use App\Models\Members;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FriendsMessage extends Model
{
    use HasFactory;

    protected $table = 'friends_message';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联用户信息
     *
     * @return void
     */
    public function member()
    {
        return $this->hasOne(Members::class, 'id', 'from_uid');
    }
}
