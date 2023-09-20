<?php

namespace App\Tool;


use Illuminate\Http\JsonResponse;


trait ResponseTrait
{
    /**
     * 加密消息
     * User: zmm
     * DateTime: 2023/7/27 15:40
     * @param  mixed $params
     * @param  string  $iv
     * @return mixed
     */
    public static function aesEncrypt(mixed $params , string $iv = ''):mixed
    {
        // 常量优先级最高
        if (defined('APP_ENCRYPT') && APP_ENCRYPT <= 0) {
            $params = (is_object($params) || is_array($params)) ? json_encode($params , 320) : $params;

            return (new Aes($iv))->encrypt($params);
        }
        if (app()->runningInConsole()) {

            if ($_SESSION['app_encrypt'] ?? 1) {
                return $params;
            }
            $params = (is_object($params) || is_array($params)) ? json_encode($params , 320) : $params;

            return (new Aes($iv))->encrypt($params);
        } else {

            return $params;
        }
    }


    /**
     * User: zmm
     * DateTime: 2022/12/1 17:31
     * @param  null  $data
     * @param  int  $code
     * @return JsonResponse
     */
    public function responseSuccess($data = null , int $code = 200) : JsonResponse
    {
        $data = ['data' => self::aesEncrypt(is_null($data) ? (object)[] : $data) , 'code' => $code , 'message' => ''];

        return response()->json($data)->setEncodingOptions(320)->header('Accept-Uuid' ,
            LARAVEL_UUID);
    }

    /**
     * DateTime: 2022/12/1 17:31
     * @param $message
     * @param  int  $code
     * @return JsonResponse
     */
    public function responseError($message , int $code = 422) : JsonResponse
    {
        $data = [
            'data'    => self::aesEncrypt((object)[]) ,
            'code'    => $code ,
            'message' => $message ,
        ];

        return response()->json($data)->setEncodingOptions(320)->header('Accept-Uuid' ,
            LARAVEL_UUID);
    }

    /**
     * 推送消息格式
     * User: zmm
     * DateTime: 2023/5/29 12:15
     * @return false|string
     */
    public static function cliSuccess(
        $data = null ,
        $eventName = TalkEventConstant::EVENT_SYSTEM ,
        null|string $uuid = ''
    ) : bool|string {
        return json_encode([
            'data'       => self::aesEncrypt(is_null($data) ? (object)[] : $data) ,
            'message'    => '' ,
            'code'       => Constant::SYSTEM_SUCCESS ,
            'event_name' => $_SESSION['event_name'] ?? $eventName ,
            'uuid'       => $_SESSION['uuid'] ?? $uuid ,
        ] , 320);
    }

    /**
     * 推送已读消息格式
     * User: zmm
     * DateTime: 2023/5/29 12:15
     * @return false|string
     */
    public static function cliReadSuccess(
        $data = null ,
        $eventName = TalkEventConstant::EVENT_SYSTEM ,
        $message_data=[]
    ) : bool|string {
        return json_encode([
            'data'       => self::aesEncrypt(is_null($data) ? (object)[] : $data) ,
            'message'    => '' ,
            'code'       => Constant::SYSTEM_SUCCESS ,
            'event_name' => $eventName ,
            'message_ids' => $message_data,
            'uuid'       => '' ,
        ] , 320);
    }

    /**
     * 推送消息格式
     * User: zmm
     * DateTime: 2023/5/29 12:15
     * @param $message
     * @param  string  $eventName
     * @param  int  $code
     * @param  string  $uuid
     * @return false|string
     */
    public static function cliError(
        $message ,
        string $eventName = TalkEventConstant::EVENT_SYSTEM ,
        int $code = 422 ,
        string $uuid = ''
    ) : bool|string {

        return json_encode([
            'data'       => self::aesEncrypt((object)[]) ,
            'message'    => $message ,
            'code'       => $code ,
            'event_name' => $_SESSION['event_name'] ?? $eventName ,
            'uuid'       => $_SESSION['uuid'] ?? $uuid ,
        ] , 320);
    }
}

