<?php


namespace App\Http\Controllers\Api;

use App\GatewayWorker\ReceiveHandleService;
use App\Http\Controllers\Controller;
use App\Jobs\SendSms;
use App\Models\AppVersion;
use App\Models\FriendsMessage;
use App\Models\Members;
use App\Models\MessageRepeater;
use App\Models\Setting;
use App\Tool\Constant;
use App\Tool\Utils;
use GatewayClient\Gateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Plugin\Manager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class V1Controller extends Controller
{

    /**
     * ucloud参数
     * User: zmm
     * DateTime: 2023/8/2 19:13
     * @return JsonResponse
     */
    public function ucloud() : JsonResponse
    {
        return $this->responseSuccess([
            'ucloud_domain'       => rtrim(config('app.url') , '/') . '/' ,
            'ucloud_bucket'       => env('UCLOUD_BUCKET' , 'june') ,
            'ucloud_proxy_suffix' => env('UCLOUD_PROXY_SUFFIX' , '.hk.ufileos.com') ,
            'ucloud_public_key'   => env('UCLOUD_PUBLIC_KEY' , '3fgt0OVVLpdHrehdsRYfovD4OkU6zgkfW15FHaXbka') ,
            'ucloud_private_key'  => env('UCLOUD_PRIVATE_KEY' ,
                'EddW6s5y0L8Am2ezZ3vK606fbJs9W9iH7K9TwzYxMblqGHw7nrVzpU4FqRoTThod6') ,
        ]);
    }

    /**
     * 发送验证码
     * User: zmm
     * DateTime: 2023/8/4 13:58
     */
    public function sendCode(Request $request) : JsonResponse
    {
        $request->validate([
            'account'  => 'bail|required|string|max:128' ,
            'sms_type' => 'bail|required|in:forget,login,other' ,
            'area'     => 'bail|nullable|string' ,
        ]);
        if (!filter_var($request['account'] , FILTER_VALIDATE_EMAIL)) {
            if (!isset(array_column(Utils::telPrefix() , 'prefix')[$request['area']])) {
                throw new BadRequestHttpException(__("手机区号错误"));
            }
            $redisKey = md5($request['account'] . ':' . $request['sms_type'].':'.$request['area']);
        } else {
            $redisKey = md5($request['account'] . ':' . $request['sms_type']);
        }
        $lock     = Cache::lock($request['account'] . '' , 3);
        try {
            if (!$lock->get()) {
                throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
            }
            if (Redis::exists($redisKey)) {
                throw new BadRequestHttpException(__("验证码5分钟有效，请勿多次发送"));
            }
            // 判断登录
            if (in_array($request['sms_type'] , ['forget' , 'other'])) {
                if (!Members::query()->when(1 , function ($query) use ($request) {
                    if (filter_var($request['account'] , FILTER_VALIDATE_EMAIL)) {
                        $query->where('email' , $request['account']);
                    } else {
                        $query->where(['area_code' => $request['area'] , 'phone' => $request['account']]);
                    }
                })->exists()) {
                    throw new BadRequestHttpException(__("账号不存在"));
                }
            }

            $params = $request->only(['account' , 'sms_type' , 'area']);
            Redis::setex($redisKey , 300 , 1);
        } catch (BadRequestHttpException $e) {
            $lock->release();
            throw new BadRequestHttpException($e->getMessage() , null , 422);

        }
        dispatch(new SendSms($params))->onQueue('SendSms');

        return $this->responseSuccess();
    }

    /**
     * 手机区号
     * User: zmm
     * DateTime: 2023/8/4 14:00
     * @return JsonResponse
     */
    public function areaCode() : JsonResponse
    {
        return $this->responseSuccess(Utils::telPrefix());
    }

    /**
     * desc:存储配置
     * author: mxm
     * Time: 2023/8/16   09:57
     */
    public function getOssInfo(): JsonResponse
    {
        $ossInfo = Setting::getOssConfigByType(['oss','aws']);
        $data = ['oss_status'=>$ossInfo['oss_status']];
        unset($ossInfo['oss_status']);
        $data['aws'] = $ossInfo;
        return $this->responseSuccess($data);
    }

    /**
     * desc:本地文件上传
     * author: mxm
     * Time: 2023/8/16   10:53
     */
    public function localUpload(Request $request): JsonResponse
    {
        $date_str = date('Y').'/'.date('m').'/'.date('d');
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $suffix = $file->getClientOriginalExtension();
            if (!$suffix) {
                throw new BadRequestHttpException(__("暂不支持文件格式"));
            }
            $fileName = $file->getClientOriginalName();
            $fileName = date('YmdHis') . '_' . md5(uniqid() . $suffix . $fileName) . '.' .$suffix;
            $file->move(public_path('storage').'/'.$date_str, $fileName);

            //域名
            $url = url('storage/'.$date_str, $fileName);
            return $this->responseSuccess(['file_name'=>$date_str.'/'.$fileName,'url'=>$url]);
        }else{
            throw new BadRequestHttpException(__("请选择要上传的文件！"));
        }
    }

    public function sendTest(){
        $data = '{
            "from_uid":157,
            "to_uid":"47",
            "talk_type":"1",
            "quote_id":0,
            "message":"哈哈哈",
            "message_type":1,
            "warn_users":"",
            "created_at":1693204239,
            "timestamp":1693204239000,
            "pwd":"",
            "id":100600,
            "is_secret":false,
            "extra":{
    
            }
        }';
        for($i = 0;$i<=10;$i++){
            $arr = json_decode($data, true);
            $arr['id'] = $arr['id']+$i+1;
            Gateway::sendToUid('47',self::cliSuccess($arr,ReceiveHandleService::TALK_MESSAGE));
        }
        dd(111);
    }

    /**
     * desc:版本信息接口
     * author: mxm
     * Time: 2023/8/29   11:43
     */
    public function getVersionInfo(Request $request): JsonResponse
    {
        $request->validate([
            'platform'  => 'bail|required|string'
        ]);
        $data = AppVersion::where(['platform'=>$request['platform']])->orderByDesc('release_date')->first(['platform','version_code','version_name','forced_update','update_url','release_date','desc']);
        return $this->responseSuccess($data);
    }

    /**
     * desc:重发消息脚本
     * author: mxm
     * Time: 2023/9/8   17:29
     */
    public function trySendMsg(Request $request){
        $max_try_num = 20;

        $idArr = MessageRepeater::where(['state'=>0])->orderBy('id')->get()->toArray();

        foreach ($idArr as $val){

            if (Gateway::isUidOnline($val['recipient_id'])) {

                $params = FriendsMessage::where('id',$val['msg_id'])->select('pwd','is_read','id','is_revoke','from_uid','to_uid','talk_type','quote_id','message_type','message','created_at','timestamp','warn_users')->first()->toArray();

                $data = ReceiveHandleService::messageStructHandle([$params]);

                $data[0]['uuid'] = $request['uuid'] ?? '';
                $data[0]['debug'] = config('app.debug');

                defined('APP_ENCRYPT') || define('APP_ENCRYPT',$data[0]['debug'] ?? config('app.debug'));

                //判断是否发送过
                if(MessageRepeater::where(['id'=>$val['id'],'state'=>1])->exists()){
                    continue;
                }

                Gateway::sendToUid($val['recipient_id'] , self::cliSuccess($data[0] , ReceiveHandleService::TALK_MESSAGE , $data[0]['uuid']));
                Log::info('发送重发机制消息',[self::cliSuccess($data[0] , ReceiveHandleService::TALK_MESSAGE , $data[0]['uuid'])]);

                DB::table('message_repeater')->where('id',$val['id'])->increment('try_num');
                if($val['try_num']+1 > $max_try_num){
                    DB::table('message_repeater')->where('id',$val['id'])->update(['state'=>2]);
                }
            }else{
                //不在线
                dd("不在线");
            }
        }
    }
}
