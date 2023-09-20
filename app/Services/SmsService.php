<?php

namespace App\Services;

use Exception;
use App\Tool\WsPush;
use App\Models\Members;
use App\Models\Setting;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class SmsService
{
    /**
     * 短信宝
     *
     * @param string $username
     * @param string $apiKey
     * @param [type] $mobile
     * @param [type] $content
     * @return void
     */
    public static function dxb($mobile, $content, $username, $apiKey)
    {
        $url = "https://api.smsbao.com/sms?u={$username}&p=$apiKey&m={$mobile}&c={$content}";
        $statusStr = array(
            "0" => "短信发送成功",
            "-1" => "参数不全",
            "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
            "30" => "密码错误",
            "40" => "账号不存在",
            "41" => "余额不足",
            "42" => "帐户已过期",
            "43" => "IP地址限制",
            "50" => "内容含有敏感词"
        );
        $result = Http::asJson()->get(
            $url
        )->body();
        // $result = '0';
        if ($result != 0) {
            // 
            return isset($statusStr[$result]) ? $statusStr[$result] : '短信发生未知错误！';
        }
        return (int) $result;
    }

    /**
     * 发送Google验证码
     *
     * @param [type] $email 接收邮箱
     * @param [type] $code  验证码
     * @return void
     */
    public static function google($email, $code)
    {
        // 
        try {
            Mail::send("SendEmailCode", ["code" => $code, "email" => $email], function (Message $message) use ($email) {
                $address = env('MAIL_FROM_ADDRESS');
                $name = trim(env('MAIL_FROM_NAME'));
                $message->from($address, $name); //此处第一个填写发送的邮箱地址，第二个填写邮箱名称，这个参数可以不填写，因为你在.env中已经配置了。
                $message->to($email);
                $message->subject("验证码");
            });
        } catch (Exception $e) {
            return '发送失败，配置信息错误';
        }
    }

    /**
     * 验证账号是否注册
     *
     * @param [type] $number
     * @param [type] $sms_type
     * @return void
     */
    public static function checkMoblieCanCode($number, $sms_type)
    {
        // 
        $member = Members::where('phone', $number)->orWhere('email', $number)->first();
        if ($member && ($sms_type == 'register')) {
            // 
            return '该账号已注册，请先登陆';
        }

        if (!$member && ($sms_type == 'forget')) {
            // 
            return '该账号未注册，请先注册';
        }

        if (!$member && ($sms_type == 'login')) {
            // 
            // return '该账号未注册，请先注册';
        }

        return '';
    }

    /* 校验签名 */
    public static function checkSign($data, $sign)
    {
        unset($data['sign']);
        // return 1;
        if ($sign == '') {
            return 0;
        }
        $key = Setting::where('key', 'sign_key')->value('value');
        $str = '';
        ksort($data);
        foreach ($data as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str .= $key;
        $newsign = md5($str);

        if ($sign == $newsign) {
            return 1;
        }
        return 0;
    }
}
