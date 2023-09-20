<?php

namespace App\Jobs;

use App\Models\Setting;
use App\Services\SendEmail;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Message;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;

class SendSms implements ShouldQueue
{
    use Dispatchable , InteractsWithQueue , Queueable , SerializesModels;

    private array $params;
    private string $code;
    private array $configArr;

    public function __construct(array $params)
    {
        $this->params = $params;
    }

    public function handleParams()
    {
        $result          = Setting::getConfig();
        $this->configArr = $configArr = array_column($result , 'value' , 'key');
        // 验证码
        if (!empty($configArr['sms_status']) && $configArr['sms_status'] == 2) {
            $this->code = str_shuffle(mt_rand(100000 , 999999));
        } else {
            $this->code = '123456';
        }
    }

    public function handle()
    {
        $this->handleParams();
        // 手机号码
        if (!filter_var($this->params['account'] , FILTER_VALIDATE_EMAIL)) {
            if (!empty($this->configArr['dxb_username']) && !empty($this->configArr['dxb_key']) && isset($this->configArr['sms_type']) && $this->configArr['sms_type'] == 2) {
                try {
                    $content   = urlencode('【' . config('app.name') . '】您的手机验证码为' . $this->code . '，有效期5分钟。');
                    $url       = "https://api.smsbao.com/sms?u={$this->configArr['dxb_username']}&p={$this->configArr['dxb_key']}&m={$this->params['account']}&c={$content}";
                    $statusStr = [
                        "0"  => "短信发送成功" ,
                        "-1" => "参数不全" ,
                        "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！" ,
                        "30" => "密码错误" ,
                        "40" => "账号不存在" ,
                        "41" => "余额不足" ,
                        "42" => "帐户已过期" ,
                        "43" => "IP地址限制" ,
                        "50" => "内容含有敏感词" ,
                    ];
                    $res       = file_get_contents($url);
                    if (!isset($statusStr[$res])) {
                        throw new Exception("发送验证码错误 " . $res);
                    }
                    if ($res) {
                        throw new Exception($statusStr[$res]);
                    }
                    Log::info($this->params['account'] . ':' . $this->params['sms_type'] . ' ' . urldecode($content));
                } catch (Exception $e) {
                    Log::info($e->getMessage() , $this->params);

                    return;
                }
            }
            Redis::setex($this->params['account'] . ':' . $this->params['sms_type'] . ':' . $this->params['area'] ,
                300 , $this->code);
        } else {
            if (!empty($this->configArr['email_status']) && $this->configArr['email_status'] == 2) {
                try {
//                    Mail::send("SendEmailCode" , ["code" => $this->code , "email" => $this->params['account']] ,
//                        function (Message $message) {
//                            $address = env('MAIL_FROM_ADDRESS');
//                            $name    = trim(env('MAIL_FROM_NAME'));
//                            $message->from($address , $name);
//                            $message->to($this->params['account']);
//                            $message->subject("验证码");
//                        });
                    $emailData['code'] = $this->code;
                    Mail::to($this->params['account'])->send(new SendEmail($emailData));
                    Log::info($this->params['account'] . ':' . $this->params['sms_type'] . ' ' . $this->code);
                } catch (Exception $e) {
                    Log::info($e->getMessage() , $this->params);

                    return;
                }
                Redis::setex($this->params['account'] . ':' . $this->params['sms_type'] , 300 , $this->code);
            }
            Redis::setex($this->params['account'] . ':' . $this->params['sms_type'] , 300 , $this->code);
        }
    }
}
