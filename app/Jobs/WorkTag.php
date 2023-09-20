<?php

namespace App\Jobs;

use App\Models\GroupsMember;
use App\Models\Members;
use App\Models\Setting;
use App\Services\SendEmail;
use App\Tool\Utils;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WorkTag implements ShouldQueue
{
    use Dispatchable , InteractsWithQueue , Queueable , SerializesModels;

    private array $params;
    private string $code;
    private array $configArr;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handle()
    {
        Log::info("操作标签的脚本:",$this->params);
        if($this->params['type'] == 1){//登陆
            $groupIdArr = GroupsMember::getUserGroup($this->params['uid']);
            if($groupIdArr){
                foreach ($groupIdArr as $val){
                    Utils::addDeviceTag($val, [$this->params['uid']]);//第一个参数群ID，第二个是用户ID集合
                }
            }

            //处理quickBox
//            $password       = str_pad($this->params['uid'] , 6 , 'aAbBcC') . '...';
//            $model = Members::query()->where(['id' => $this->params['uid']])->first();
//            $quickbloxLogin = env('REGISTER_NAME') . $this->params['uid'];
//            if ($model->quickblox_id == 0) {
//                $result = Http::asJson()->retry(2 , mt_rand(10 ,
//                    1000))->withHeaders(['Authorization' => 'ApiKey ' . env('QUICKBLOX_API_KEY')])->post(rtrim(env('QUICKBLOX_URL') ,
//                        '/') . '/users.json' ,
//                    ['user' => ['login' => $quickbloxLogin , 'password' => $password]])->json();
//
//                if (!empty($result['user']['id'])) {
//                    $model->setAttribute('quickblox_id' , $result['user']['id']);
//                    $model->save();
//                } else {
//                    Log::info("quickblox获取数据错误，请稍后再试");
//                }
//            }

        }else{//登出
            $groupIdArr = GroupsMember::getUserGroup($this->params['uid']);
            if($groupIdArr){
                foreach ($groupIdArr as $val){
                    Utils::removeDeviceTag($this->params['uid'], intval($val));////移除群标签
                }
            }
        }
    }
}
