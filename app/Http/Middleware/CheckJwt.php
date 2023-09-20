<?php


namespace App\Http\Middleware;


use App\Models\Members;
use App\Tool\Aes;
use App\Tool\Constant;
use App\Tool\Jwt;
use App\Tool\ResponseTrait;
use App\Tool\Utils;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;


class CheckJwt
{
    use ResponseTrait;

    public function handle(Request $request , Closure $next)
    {
        // debug模式
        define('APP_ENCRYPT' , $request->header('app-encrypt' , config('app.debug')));
        // 解析数据
        if (APP_ENCRYPT <= 0) {
            if ($request['params_body']) {
                $tmpArr = json_decode((new Aes())->decrypt($request['params_body']) , 1);
            } else {
                $tmpArr = [];
                foreach ($request->input() as $k => $v) {
                    $tmpArr[$k] = json_decode((new Aes())->decrypt($v) , 1);
                }
            }
            $tmpArr && $request->merge($tmpArr);
        }
        Log::channel('web')->info(LARAVEL_UUID , ['request' => $request->input()]);
        $path = $request->path();
        // 设置请求头
        if ('api/uploadFile' != $path) {
            $request->headers->set('Accept' , 'application/json');
        }
        // 不校验jwt
        if (!in_array($path , ['api/register' , 'api/login' , 'api/forgetPassword' , 'api/sms' , 'api/getVersionInfo'])) {
            // 请求参数错误
            $jwt = $request->header('Authorization');
            if (!$jwt) {
                return $this->responseError(Constant::CODE_ARR[Constant::CODE_401] , Constant::CODE_401);
            }
            // 校验jwt错误
            $jwt    = explode('Bearer ' , $jwt)[1];
            $result = Jwt::verifyToken($jwt);
            if (!$result) {
                return $this->responseError(Constant::CODE_ARR[Constant::CODE_402] , Constant::CODE_402);
            }
            // 判断是否缓存过期
            $cacheMd5 = Redis::get(Utils::getMemberKey($result['uid'] , $result['platform']));
            if (!$cacheMd5) {
                return $this->responseError(Constant::CODE_ARR[Constant::CODE_402] , Constant::CODE_402);
            }
            // 不是最新的jwt错误
            if ($cacheMd5 !== md5($jwt)) {
                return $this->responseError(Constant::CODE_ARR[Constant::CODE_403] , Constant::CODE_403);
            }
            $memberInfo = array_merge(Members::getCacheInfo($result['uid']) , ['platform' => $result['platform']]);
            $request->merge(['user_info' => $memberInfo , 'uid' => $result['uid']]);
        }

        $response = $next($request);

        Log::channel('web')->info(LARAVEL_UUID , ['response' => json_decode($response->getContent() , 1) ,]);


        return $response;
    }
}
