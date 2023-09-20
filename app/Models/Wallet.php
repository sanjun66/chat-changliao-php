<?php

namespace App\Models;

use App\Tool\Constant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{

    use HasFactory;

    protected $table = 'wallets';
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
        $model->currency = $data['currency'];
        $model->address = $data['address'];
        $model->withdrawal_address = $data['withdrawal_address'];
        $model->save();
    }

    /**
     * desc:注册钱包地址
     * author: mxm
     * Time: 2023/9/14   11:36
     */
    public static function registerWallet($uid){
        foreach (constant::CURRENCY_ARR as $v){
            //注册用户钱包
            if(!Wallet::where(['uid'=>$uid,'currency'=>$v])->exists()){
                self::saveData(['uid'=>$uid,'currency'=>$v,'address'=>'','withdrawal_address'=>'']);
            }
        }
    }
}