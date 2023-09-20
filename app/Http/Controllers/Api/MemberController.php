<?php


namespace App\Http\Controllers\Api;

use App\GatewayWorker\ReceiveHandleService;
use App\Http\Controllers\Controller;
use App\Jobs\LoginParams;
use App\Jobs\SendMsg;
use App\Jobs\SendSms;
use App\Jobs\WorkTag;
use App\Models\Friends;
use App\Models\FriendsMessage;
use App\Models\Groups;
use App\Models\GroupsMember;
use App\Models\Members;
use App\Models\MembersInfo;
use App\Models\MembersToken;
use App\Models\Setting;
use App\Models\TalkRecordsAudio;
use App\Models\TalkRecordsFile;
use App\Models\TalkRecordsLogin;
use App\Models\Wallet;
use App\Tool\Constant;
use App\Tool\Jwt;
use App\Tool\UfileSdk\Ucloud;
use App\Tool\Utils;
use Exception;
use getID3;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use zjkal\TimeHelper;

class MemberController extends Controller
{
    /**
     * 登录
     * User: zmm
     * DateTime: 2023/5/29 10:04
     * @param  Request  $request
     * @return JsonResponse
     */
    public function login(Request $request) : JsonResponse
    {
        /**
         * https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2#Officially_assigned_code_elements
         * 1、密码登录
         * 2、注册即登录
         */
        $request->validate([
            'type'            => 'bail|required|int|in:1,2' ,
            'login_way'       => 'bail|required|string|in:email,phone' ,
            'platform'        => 'bail|required|in:h5,ios,mac,web,android' ,
            'registration_id' => 'bail|nullable|string|max:128' ,
        ]);

        if (1 == $request['type']) {
            $lock = Cache::lock(__FUNCTION__ . md5(json_encode($request->only([
                    'login_way' ,
                    'email' ,
                    'phone' ,
                    'area_code' ,
                ]))) , 3);
            if (!$lock) {
                throw new BadRequestHttpException("请求频繁，请稍后再试");
            }
            $model = (function () use ($request) {
                $request->validate([
                    'password' => [
                        'bail' ,
                        'required' ,
                        'string' ,
                        Password::min(6)->letters()->numbers() ,
                    ] ,
                ]);
                if ('email' === $request['login_way']) {
                    $request->validate([
                        'email' => 'bail|required|email|max:128' ,
                    ]);
                    $model = Members::query()->where($request->only('email'))->firstOr(['*'] , function () {
                        throw new BadRequestHttpException("电子邮箱账号不存在");
                    });
                } else {
                    $request->validate([
                        'phone'     => 'bail|required|string|max:64|regex:/^[0-9]{6,14}[0-9]$/' ,
                        'area_code' => 'bail|required|string|max:64' ,
                    ]);
                    $model = Members::query()->where([
                        'area_code' => $request['area_code'] ,
                        'phone'     => $request['phone'] ,
                    ])->firstOr(['*'] , function () {
                        throw new BadRequestHttpException("手机账号不存在");
                    });
                }
                if (!Hash::check($request['password'] , $model['password'])) {
                    throw new BadRequestHttpException("登录密码错误");
                }

                return $model;
            })();
        } else {
            $lock = Cache::lock(__FUNCTION__ . md5(json_encode($request->only([
                    'login_way' ,
                    'email' ,
                    'phone' ,
                    'area_code' ,
                ]))) , 3);
            if (!$lock) {
                throw new BadRequestHttpException("请求频繁，请稍后再试");
            }
            $model = (function () use ($request) {
                $request->validate([
                    'email'     => 'bail|nullable|email|max:128' ,
                    'area_code' => 'bail|nullable|string|max:64' ,
                    'code'      => 'bail|required|string|size:6' ,
                ]);
                if ('email' === $request['login_way']) {
                    $request->validate([
                        'email' => 'bail|required|email|max:128' ,
                    ]);
                    $redisKey = $request['email'] . ':login';
                    if (2 == Setting::query()->where('key' , 'email_status')->value('value')) {
                        if (Redis::get($redisKey) !== $request['code']) {
                            throw new BadRequestHttpException("邮箱验证码错误");
                        }
                    }
                    $model = Members::query()->where(['email' => $request['email']])->first();
                    if (!$model) {
                        $model = Members::query()->create([
                            'email'       => $request['email'] ,
                            'register_ip' => $request->getClientIp() ,
                        ]);
                        $model->setAttribute('nick_name' , config('app.name') . $model['id']);
                        $model->setAttribute('account' , Utils::enCode($model['id']));
                        $model->save();
                    }

                } else {
                    $request->validate([
                        'phone'     => 'bail|required|string|max:64|regex:/^[0-9]{6,14}[0-9]$/' ,
                        'area_code' => 'bail|required|string|max:64' ,
                    ]);
                    $redisKey = $request['phone'] . ':login:' . $request['area_code'];
                    if (2 == Setting::query()->where('key' , 'sms_type')->value('value')) {
                        if (Redis::get($redisKey) !== $request['code']) {
                            throw new BadRequestHttpException("手机验证码错误");
                        }
                    }
                    $model = Members::query()->where([
                        'phone'     => $request['phone'] ,
                        'area_code' => $request['area_code'] ,
                    ])->first();
                    if (!$model) {
                        $model = Members::query()->create([
                            'phone'       => $request['phone'] ,
                            'area_code'   => $request['area_code'] ,
                            'register_ip' => $request->getClientIp() ,
                        ]);
                        $model->setAttribute('nick_name' , config('app.name') . $model['id']);
                        $model->setAttribute('account' , Utils::enCode($model['id']));
                        $model->save();
                    }
                }

                Redis::del([$redisKey , md5($redisKey)]);

                return $model;
            })();
        }

        // 踢出旧的设备
        Utils::popOldClient($model['id'] , $request['platform']);
        DB::beginTransaction();
        // 登录日志
        TalkRecordsLogin::query()->insert([
            'uid'      => $model['id'] ,
            'platform' => $request['platform'] ,
            'ip'       => $request->getClientIp() ,
            'agent'    => $request->header('User-Agent' , 'unknown') ,
        ]);
        // 设置jwt
        $token = Jwt::getToken($model['id'] , ['platform' => $request['platform']]);
        // 创建数据
        MembersToken::query()->updateOrCreate([
            'uid'      => $model['id'] ,
            'platform' => $request['platform'] ,
        ] , [
            'uid'       => $model['id'] ,
            'crc32'     => crc32($token) ,
            'platform'  => $request['platform'] ,
            'expire_at' => date('Y-m-d H:i:s' , time() + Jwt::getExpireSeconds()) ,
        ]);
        // 用户详细信息
        MembersInfo::query()->updateOrCreate(['uid'=>$model['id']],['uid'=>$model['id']]);
        DB::commit();
        // 缓存数据
        Redis::setex(Utils::getMemberKey($model['id'] , $request['platform']) , Jwt::getExpireSeconds() , md5($token));
        $password       = str_pad($model['id'] , 6 , 'aAbBcC') . '...';
        $quickbloxLogin = env('REGISTER_NAME') . $model['id'];

        if (empty($model['quickblox_id'])) {
            $result = Http::asJson()->retry(2 , mt_rand(10 ,
                1000))->withHeaders(['Authorization' => 'ApiKey ' . env('QUICKBLOX_API_KEY')])->post(rtrim(env('QUICKBLOX_URL') ,
                    '/') . '/users.json' ,
                ['user' => ['login' => $quickbloxLogin , 'password' => $password]])->json();

            if (!empty($result['user']['id'])) {
                $model->setAttribute('quickblox_id' , $result['user']['id']);
                $model->save();
            } else {
                throw new BadRequestHttpException("获取数据错误，请稍后再试");
            }
        }
        // 强制刷新缓存
        Members::getCacheInfo($model['id'] , true);

        $lock->release();

        Redis::sadd(sprintf(Constant::MEMBERS_DEVICE , $model['id']) , $request['platform']);

        $request['registration_id'] && dispatch(new LoginParams($model['id'],$request['registration_id']))->onQueue('LoginParams');

        // 登陆添加标签
        $tagParams['uid'] = $model['id'];
        $tagParams['type'] = '1';//1-登陆 2-退出
        dispatch(new WorkTag($tagParams))->onQueue('WorkTag');

        return $this->responseSuccess([
            'uid'             => $model['id'] ,
            'token'           => $token ,
            'expire_seconds'  => Jwt::getExpireSeconds() ,
            'platform'        => $request['platform'] ,
            'quickblox_login' => $quickbloxLogin ,
            'quickblox_id'    => empty($model['quickblox_id']) ? ($result['user']['id'] ?? '') : $model['quickblox_id'] ,
            'quickblox_pwd'   => $password ,
            'rong_yun_token'  => '',
            'registration_id' => Utils::getMemberRegistrationId($model['id']),
        ]);
    }

    /**
     * 文件上传
     * User: zmm
     * DateTime: 2023/5/29 10:35
     * @param  Request  $request
     * @return JsonResponse
     * @throws Exception
     */
    public function uploadFile(Request $request) : JsonResponse
    {
        $params = [
            'to_uid'     => 'bail|required|int|min:1' ,
            'quote_id'   => 'bail|required|int|min:0' ,
            'message'    => 'bail|required|string|max:1024' ,
            'warn_users' => 'bail|nullable|string|max:512' ,
            'talk_type'  => 'bail|required|int|in:1,2' ,
            'duration'   => 'bail|nullable|int|min:0' ,
            'weight'     => 'bail|nullable|int|min:0' ,
            'height'     => 'bail|nullable|int|min:0' ,
            'type'       => 'bail|required|int|min:1|max:4' ,
            'pwd'        => 'bail|nullable|string|min:4|max:16' ,

        ];
        $request->validate($params);
        // 判断是否好友
        Friends::isFriendOrGroupMember($request['uid'] , $request['to_uid'] , $request['talk_type']);
        // 上传文件
        $file = $request->file('image');
        if (!$file || !$file->isvalid()) {
            throw new BadRequestHttpException(__("文件未上传成功"));
        }
        $suffix = $file->getClientOriginalExtension();
        if (!$suffix) {
            throw new BadRequestHttpException(__("暂不支持文件格式"));
        }
        $size         = $file->getSize();
        $originalName = $file->getClientOriginalName();
        if (!in_array(strtolower($suffix) , [
            "png" ,
            "jpg" ,
            "bmp" ,
            "cod" ,
            "gif" ,
            "jpe" ,
            "jpeg" ,
            "jfif" ,
            "svg" ,
            "tif" ,
            "tiff" ,
            "ras" ,
            "ico" ,
            "pgm" ,
            "pnm" ,
            "ppm" ,
            "xbm" ,
            "xpm" ,
            "xwd" ,
            "rgb" ,
            "log" ,
            "txt" ,
            "html" ,
            "stm" ,
            "uls" ,
            "bas" ,
            "c" ,
            "h" ,
            "rtf" ,
            "sct" ,
            "tsv" ,
            "htt" ,
            "htc" ,
            "etx" ,
            "vcf" ,
            "mp3" ,
            "au" ,
            "snd" ,
            "mid" ,
            "rmi" ,
            "aif" ,
            "aifc" ,
            "m3u" ,
            "ra" ,
            "ram" ,
            "wav" ,
            "wma" ,
            "pdf" ,
            "doc" ,
            "docx" ,
            "dot" ,
            "dotx" ,
            "xls" ,
            "xlsx" ,
            "xlc" ,
            "xlm" ,
            "xla" ,
            "xlt" ,
            "xlw" ,
            "mp4" ,
            "mov" ,
            "rmvb" ,
            "avi" ,
            "mp2" ,
            "xpa" ,
            "xpe" ,
            "mpeg" ,
            "mpg" ,
            "mpv2" ,
            "qt" ,
            "lsf" ,
            "lsx" ,
            "asf" ,
            "asr" ,
            "asx" ,
            "wmv" ,
            "movie" ,
            "ppt" ,
            "pptx" ,
            "pages" ,
            "numbers" ,
            "key" ,
        ])) {
            // throw new BadRequestHttpException(__("此文件格式不支持"));
        }
        $url = date('YmdHis') . '_' . md5(uniqid() . $suffix . $size . $originalName) . '.' . $suffix;
        // 判断上传文件格式
        if (in_array($suffix , ['mp4' , 'avi' , 'wmv' , 'flv'])) {
            $getID3   = new getID3();
            $fileInfo = $getID3->analyze(request()->file('image')->getRealPath());
            if ($fileInfo && is_array($fileInfo)) {
                if (!empty($fileInfo['error'])) {
                    throw new BadRequestHttpException(__("文件损坏暂不支持上传[1]"));
                }
                if (empty($fileInfo['video'])) {
                    throw new BadRequestHttpException(__("文件损坏暂不支持上传[2]"));
                }
                $cover               = cover_upload($file->getRealPath() , $fileInfo['video']['resolution_x'] ,
                    $fileInfo['video']['resolution_y']);
                $request['duration'] = $fileInfo['playtime_seconds'];
                $request['weight']   = $fileInfo['video']['resolution_x'];
                $request['height']   = $fileInfo['video']['resolution_y'];
            }
        }
        // 上传到云
        Ucloud::uploadFile($url);
        $result = [
            'uid'           => $request['uid'] ,
            'suffix'        => $suffix ,
            'original_name' => $originalName ,
            'type'          => $request['type'] ,
            'size'          => $size ,
            'url'           => $url ,
            'path'          => $request['path'] ?? '' ,
            'height'        => $request['height'] ?? 0 ,
            'weight'        => $request['weight'] ?? 0 ,
            'duration'      => $request['duration'] ?? 0 ,
            'created_at'    => time() ,
        ];
        DB::beginTransaction();
        unset($params['duration'] , $params['height'] , $params['weight'] , $params['type']);
        // 插入数据id
        $timeStamp = TimeHelper::getMilliTimestamp();
        $id        = FriendsMessage::query()->insertGetId(array_merge($request->only(array_keys($params)) ,
            [
                'from_uid'     => $request['uid'] ,
                'to_uid'       => $request['to_uid'] ,
                'message_type' => 2 ,
                'timestamp'    => $timeStamp ,
                'pwd'          => $request['pwd'] ?? '' ,
            ]));
        TalkRecordsFile::query()->insert([
                'cover'      => $cover ?? '' ,
                'record_id'  => $id ,
                'created_at' => date('Y-m-d H:i:s') ,
            ] + $result);
        DB::commit();

        $data = [
            'id'           => $id ,
            'from_uid'     => $request['uid'] ,
            'to_uid'       => $request['to_uid'] ,
            'talk_type'    => $request['talk_type'] ,
            'quote_id'     => $request['quote_id'] ,
            'message_type' => Constant::FILE_MESSAGE ,
            'message'      => $request['message'] ,
            'created_at'   => $result['created_at'] ,
            'warn_users'   => $request['warn_users'] ?? '' ,
            'timestamp'    => $timeStamp ,
            'pwd'          => $request['pwd'] ?? '' ,
        ];
        $data = ReceiveHandleService::messageStructHandle([$data]);
        $data = array_pop($data);
        dispatch(new SendMsg($data + [
                'uuid'  => $request['uuid'] ?? null ,
                'debug' => APP_ENCRYPT ,
            ]))->onQueue('SendMsg');

        return $this->responseSuccess($data + ['uuid' => $request['uuid'] ?? '']);
    }

    /**
     * 用户信息查看 修改
     * User: zmm
     * DateTime: 2023/5/29 10:35
     * @param  Request  $request
     * @return JsonResponse
     */
    public function userInfo(Request $request) : JsonResponse
    {
        if (in_array($request->method() , ['GET' , 'POST'])) {
            $request->validate(['id' => 'bail|nullable|int']);
            if ($request['id']) {
                $memberInfo              = Members::getCacheInfo($request['id']);
                $friendsModel            = Friends::query()->where([
                    'uid'       => $request['uid'] ,
                    'friend_id' => $request['id'] ,
                ])->first(['remark' , 'is_black', 'is_disturb']);
                $memberInfo['note_name'] = $friendsModel['remark'] ?? '';
                $memberInfo['is_friend'] = $friendsModel ? 1 : 0;
                $memberInfo['is_black']  = $friendsModel['is_black'] ?? 0;
                $memberInfo['is_disturb']  = $friendsModel['is_disturb'] ?? 0;
            } else {
                $memberInfo              = $request['user_info'];
                $memberInfo['note_name'] = '';
                $memberInfo['is_friend'] = 0;
                $memberInfo['is_black']  = 0;
            }

            unset($memberInfo['password'] , $memberInfo['register_ip'] , $memberInfo['created_at'] , $memberInfo['updated_at'] , $memberInfo['platform']);
            $memberInfo['quickblox_login'] = 'Laravel' . $memberInfo['id'];
            return $this->responseSuccess($memberInfo);
        }
        $params = [
            'apply_auth' => 'bail|nullable|int:1,0' ,
            'nick_name'  => 'bail|nullable|string|max:20' ,
            'avatar'     => 'bail|nullable|string|max:128' ,
            'account'    => 'bail|nullable|string|max:16' ,
            'sex'        => 'bail|nullable|int|in:0,1,2' ,
            'age'        => 'bail|nullable|int|max:65535|min:0' ,
            'address'    => 'bail|nullable|string|max:128' ,
            'sign'       => 'bail|nullable|string|max:128' ,
        ];
        if ($request['account'] && $request['user_info']['account'] != $request['account']) {
            if (Members::query()->where('account' , $request['account'])->exists()) {
                throw new BadRequestHttpException(__("账号也被占用，请重新换一个"));
            }
        }
        $request->validate($params);
        $memberInfo = Members::query()->find($request['uid']);
        foreach ($memberInfo->getAttributes() as $k => $v) {
            if ($v != $request[$k] && !is_null($request[$k])) {
                $memberInfo->setAttribute($k , $request[$k]);
            }
        }
        $memberInfo->save();
        if ($request['nick_name']) {
            Friends::query()->where(['friend_id' => $request['uid']])->update(['remark' => $request['nick_name']]);
            // Utils::modifyName($request['uid'],$request['nick_name']);
        }
        Members::getCacheInfo($request['uid'] , true);

        return $this->responseSuccess();

    }

    /**
     * 刷新token
     * User: zmm
     * DateTime: 2023/6/5 14:56
     * @param  Request  $request
     * @return JsonResponse
     */
    public function refreshToken(Request $request) : JsonResponse
    {
        $lock = Cache::lock(__FUNCTION__ , 10);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }
        $token = Jwt::getToken($request['uid'] , ['platform' => $request['user_info']['platform']]);
        $key   = Utils::getMemberKey($request['uid'] , $request['user_info']['platform']);
        Redis::setex($key , Jwt::getExpireSeconds() , md5($token));
        Utils::popOldClient($request['uid'] , $request['user_info']['platform']);

        // 刷新token
        MembersToken::query()->updateOrCreate([
            'uid'      => $request['uid'] ,
            'platform' => $request['user_info']['platform'] ,
        ] , [
            'uid'       => $request['uid'] ,
            'crc32'     => crc32($token) ,
            'platform'  => $request['user_info']['platform'] ,
            'expire_at' => date('Y-m-d H:i:s' , time() + Jwt::getExpireSeconds()) ,
        ]);

        $lock->release();

        return $this->responseSuccess([
            'uid'            => $request['uid'] ,
            'token'          => $token ,
            'expire_seconds' => Jwt::getExpireSeconds() ,
            'platform'       => $request['user_info']['platform'] ,
        ]);
    }

    /**
     * 修改密码
     * User: zmm
     * DateTime: 2023/6/5 15:04
     * @param  Request  $request
     * @return JsonResponse
     */
    public function modifyPassword(Request $request) : JsonResponse
    {
        $request->validate([
            'old_password' => ['bail' , 'required' , 'string' , Password::min(6)->letters()->numbers()] ,
            'new_password' => ['bail' , 'required' , 'string' , Password::min(6)->letters()->numbers()] ,
        ]);
        $lock = Cache::lock(__FUNCTION__ . $request['uid'] , 1);
        if (!$lock->get()) {
            throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
        }
        $model = Members::query()->where('id' , $request['uid'])->firstOr(['*'] , function () {
            throw new BadRequestHttpException("账号不存在");
        });
        if (!Hash::check($request['old_password'] , $model['password'])) {
            throw new BadRequestHttpException("原密码错误");
        }
        $model->setAttribute('password' , Hash::make($request['new_password']));
        $model->save();
        Utils::popAllClient($model['id']);

        $lock->release();

        return $this->responseSuccess();
    }

    /**
     * 忘记密码
     * User: zmm
     * DateTime: 2023/6/5 15:04
     * @param  Request  $request
     * @return JsonResponse
     */
    public function forgetPassword(Request $request) : JsonResponse
    {
        $request->validate([
            'login_way' => 'bail|required|string|in:email,phone' ,
            'code'      => ['bail' , 'required' , 'string' , 'size:6'] ,
            'password'  => ['bail' , 'required' , 'string' , Password::min(6)->letters()->numbers()] ,
        ]);

        if ('email' == $request['login_way']) {
            $lock = Cache::lock(__FUNCTION__ . $request['login_way'] . $request['email'] , 3);
            if (!$lock->get()) {
                throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
            }
            $request->validate(['email' => 'bail|required|email|max:128' ,]);
            $model = Members::query()->where('email' , $request['email'])->firstOr(['*'] , function () {
                throw new BadRequestHttpException("电子邮箱错误");
            });
        } else {
            $lock = Cache::lock(__FUNCTION__ . $request['login_way'] . $request['phone'] , 3);
            if (!$lock->get()) {
                throw new BadRequestHttpException(__("请求频繁请求，请稍后再试"));
            }
            $request->validate([
                'phone'     => 'bail|required|string|max:64|regex:/^[0-9]{6,14}[0-9]$/' ,
                'area_code' => 'bail|required|string|max:64' ,
            ]);
            $model = Members::query()->where([
                'area_code' => $request['area_code'] ,
                'phone'     => $request['phone'] ,
            ])->firstOr(['*'] , function () {
                throw new BadRequestHttpException("手机号码错误");
            });
        }
        // todo 校验验证码
        if (!app()->environment('local')) {

        }

        $model->setAttribute('password' , Hash::make($request['password']));
        $model->save();

        Utils::popAllClient($model['id']);
        $lock->release();

        return $this->responseSuccess();
    }

    /**
     * 修改头像
     * User: zmm
     * DateTime: 2023/6/13 16:15
     * @param  Request  $request
     * @return JsonResponse
     */
    public function modifyAvatar(Request $request) : JsonResponse
    {
        $type = $request->input('type' , 1);//1-用户头像，2-群头像

        $file = $request->file('image');
        if (!$file || !$file->isvalid()) {
            throw new BadRequestHttpException(__("图片未上传成功"));
        }
        $suffix = $file->getClientOriginalExtension();
        if (!$suffix) {
            throw new BadRequestHttpException(__("暂不支持文件格式"));
        }
        $size         = $file->getSize();
        $originalName = $file->getClientOriginalName();
        if (false === stripos('gif,jpeg,jpg,png' , $suffix)) {
            throw new BadRequestHttpException(__("图片格式不支持"));
        }
        $url = date('YmdHis') . '_' . md5(uniqid() . $suffix . $size . $originalName) . '.' . $suffix;
        Ucloud::uploadFile($url);
        if (1 == $type) {
            Members::query()->where('id' , $request['uid'])->update(['avatar' => $url]);
            Members::getCacheInfo($request['uid'] , true);
        }

        return $this->responseSuccess([
            'avatar'     => Utils::getAvatarUrl($url) ,
            'image_name' => $url ,
        ]);
    }

    /**
     * 新修改头像
     * User: zmm
     * DateTime: 2023/6/13 16:15
     * @param  Request  $request
     * @return JsonResponse
     */
    public function modifyAvatarNew(Request $request) : JsonResponse
    {
        $type = $request->input('type' , 1);//1-用户头像，2-群头像
        $driver = $request->input('driver' , '');//存储方式
        $image = $request->input('image' , '');//文件名
        if($driver == ''){
            throw new BadRequestHttpException(__("存储方式不存在"));
        }
        if($image == ''){
            throw new BadRequestHttpException(__("图片名称不存在"));
        }
//        $file = $request->file('image');
//        if (!$file || !$file->isvalid()) {
//            throw new BadRequestHttpException(__("图片未上传成功"));
//        }
//        $suffix = $file->getClientOriginalExtension();
//        if (!$suffix) {
//            throw new BadRequestHttpException(__("暂不支持文件格式"));
//        }
//        $size         = $file->getSize();
//        $originalName = $file->getClientOriginalName();
//        if (false === stripos('gif,jpeg,jpg,png' , $suffix)) {
//            throw new BadRequestHttpException(__("图片格式不支持"));
//        }
//        $url = date('YmdHis') . '_' . md5(uniqid() . $suffix . $size . $originalName) . '.' . $suffix;
//        Ucloud::uploadFile($url);
        if (1 == $type) {
            Members::query()->where('id' , $request['uid'])->update(['avatar' => $image,'driver'=>$driver]);
            Members::getCacheInfo($request['uid'] , true);
        }

        return $this->responseSuccess([
            'avatar'     => Utils::getAvatarUrl($image,$driver) ,
            'image_name' => $image ,
        ]);
    }

    /**
     * 检测群聊或者私聊
     * User: zmm
     * DateTime: 2023/7/26 10:59
     * @param  Request  $request
     * @return JsonResponse
     */
    public function checkParams(Request $request) : JsonResponse
    {
        $request->validate([
            'talk_type'    => 'bail|required|in:1,2' ,
            'id'           => 'bail|required|max:512' ,
            'message_type' => 'bail|required|in:10,11' ,
        ]);
        if ($request['talk_type'] == Constant::TALK_PRIVATE) {
            // from把to 拉黑了 from可以发消息 to不可以发消息
            if (!Friends::query()->where(['uid' => $request['uid'] , 'friend_id' => $request['id']])->exists()) {
                $message = "不是好友无法聊天";
                throw new BadRequestHttpException(__($message));
            }
            // 不是好友
            $result = Friends::query()->where([
                'uid'       => $request['id'] ,
                'friend_id' => $request['uid'] ,
            ])->value('is_black');
            if (is_null($result)) {
                $message = "你还不是他（她）朋友，请先加好友";
                throw new BadRequestHttpException(__($message));
            }
            if ($result) {
                $message = "对方把您加入黑名单了";
                throw new BadRequestHttpException(__($message));
            }
        } else {
            $request->validate(['group_id' => 'bail|required|int|min:1']);
            //todo 我往群聊发
            $role = GroupsMember::query()->where(['uid' => $request['uid'],'group_id'=>$request['group_id']])->value('role');

            $res = Groups::query()->from('groups' , 'g')->join('groups_member as m' , 'g.id' , '=' ,
                'm.group_id')->where([
                'm.uid'      => $request['uid'] ,
                'm.group_id' => $request['group_id'] ,
            ])->first(['m.is_mute' , 'g.uid' , 'g.is_dismiss' , 'g.is_mute as g_mute' , 'g.is_audio']);
            if (empty($res)) {
                $message = "群聊不存在";

                throw new BadRequestHttpException(__($message));
            }
            if ($res['g_mute'] && $role == 0) {
                $message = "群聊已被禁言";

                throw new BadRequestHttpException(__($message));
            }
            if ($res['is_mute'] && $role == 0) {
                $message = "你已被禁言";

                throw new BadRequestHttpException(__($message));
            }
            if ($res['is_dismiss']) {
                $message = "群聊已解散";

                throw new BadRequestHttpException(__($message));
            }
            // 判断群主是否有权限
            $memberInfo = MembersInfo::query()->where(['uid'=>$res['uid']])->first(['audio_expire','audio']);
            if ($memberInfo) {
                if (!$memberInfo['audio']) {
                    $message = '群主暂未拥有音频权限';
                    throw new BadRequestHttpException(__($message));
                }
                if (time() >= $memberInfo['audio_expire']) {
                    $message = '音频权限已过期';
                    throw new BadRequestHttpException(__($message));
                }
            }

            if (!$res['is_audio']) {
                $message = "音频未开启";

                throw new BadRequestHttpException(__($message));
            }
            $request['id'] = join(',' , GroupsMember::query()->whereIn('uid' ,
                parse_ids($request['id'] . ',' . $request['uid']))->where('group_id' ,
                $request['group_id'])->pluck('uid')->toArray());
        }

        // 创建消息
        DB::beginTransaction();
        $data       = [
            'from_uid'     => $request['uid'] ,
            'to_uid'       => $request['talk_type'] == Constant::TALK_PRIVATE ? $request['id'] : $request['group_id'] ,
            'talk_type'    => $request['talk_type'] ,
            'message_type' => $request['message_type'] ,
            'warn_users'   => $request['warn_users'] ?? '' ,
            'created_at'   => date('Y-m-d H:i:s') ,
            'timestamp'    => TimeHelper::getMilliTimestamp() ,
        ];
        $data['id'] = FriendsMessage::query()->insertGetId($data);
        TalkRecordsAudio::query()->insert(['record_id' => $data['id'] , 'user_ids' => $request['id']]);
        DB::commit();
        $sendData = ReceiveHandleService::messageStructHandle([$data]);
        $sendData = array_pop($sendData);

        $sendData += ['uuid' => $request['uuid'] ?? null , 'debug' => APP_ENCRYPT];
        if ($request['talk_type'] == Constant::TALK_GROUP) {
            dispatch(new SendMsg($sendData))->onQueue('SendMsg');
        }
        unset($sendData['debug']);

        return $this->responseSuccess($sendData);

    }

    /**
     * 更新对话状态
     * User: zmm
     * DateTime: 2023/8/2 18:12
     * @param  Request  $request
     * @return JsonResponse
     */
    public function talkState(Request $request) : JsonResponse
    {
        $request->validate(['id' => 'bail|required|int|min:1' , 'state' => 'bail|required|int|min:1|max:7']);
        $audioModel   = TalkRecordsAudio::query()->where('record_id' , $request['id'])->firstOr(['*'] , function () {
            throw new BadRequestHttpException(__("找不到通话记录"));
        });
        $messageModel = FriendsMessage::query()->find($request['id'] , [
            'id' ,
            'from_uid' ,
            'to_uid' ,
            'talk_type' ,
            'quote_id' ,
            'message_type' ,
            'message' ,
            'created_at' ,
            'warn_users' ,
            'timestamp' ,
            'pwd' ,
        ]);
        if ($request['state'] == $audioModel['state']) {
            throw new BadRequestHttpException(__("更新通话状态错误"));
        }
        if ($messageModel['talk_type'] == Constant::TALK_PRIVATE) {
            if (!in_array($audioModel['state'] , [0 , 4])) {
                throw new BadRequestHttpException(__("更新通话状态失败"));
            }
            if ($request['state'] != 4) {
                $audioModel['state'] == 4 && $duration = time() - $audioModel['begin_time'];
                isset($duration) && $audioModel->setAttribute('duration' , $duration);
            } else {
                $audioModel->setAttribute('begin_time' , time());
            }
            if (!$audioModel['state'] && time() - 120 >= strtotime($audioModel['created_at'])) {
                $audioModel->setAttribute('state' , 6);
                Log::info('通话异常 ' . $request['id']);
            } else {
                $audioModel->setAttribute('state' , $request['state']);
            }
            $audioModel->save();
            $audioModel->refresh();
            $sendData = ReceiveHandleService::messageStructHandle([$messageModel->toArray()]);
            $sendData = array_pop($sendData);
            $sendData += ['uuid' => $request['uuid'] ?? null , 'debug' => APP_ENCRYPT];
            $audioModel['state'] != 4 && dispatch(new SendMsg($sendData))->onQueue('SendMsg');
        } else {
            $lock = Cache::lock('group:msg:' . $request['id'] , 1);
            if (!$lock->get()) {
                sleep(1);
            }
            $sendData = Redis::get(__FUNCTION__ . ':' . $request['id']);
            if ($sendData) {
                $sendData = json_decode($sendData , 1);
                goto START;
            }
            // 创建消息
            DB::beginTransaction();
            $data       = [
                'from_uid'     => $request['uid'] ,
                'to_uid'       => $messageModel['to_uid'] ,
                'talk_type'    => $messageModel['talk_type'] ,
                'message_type' => $messageModel['message_type'] ,
                'warn_users'   => $messageModel['warn_users'] ?? '' ,
                'created_at'   => date('Y-m-d H:i:s') ,
                'timestamp'    => TimeHelper::getMilliTimestamp() ,
            ];
            $data['id'] = FriendsMessage::query()->insertGetId($data);
            TalkRecordsAudio::query()->insert([
                'record_id' => $data['id'] ,
                'user_ids'  => $audioModel['user_ids'] ,
                'state'     => $request['state'] ,
            ]);
            DB::commit();
            $sendData = ReceiveHandleService::messageStructHandle([$data]);
            $sendData = array_pop($sendData);
            $sendData += ['uuid' => $request['uuid'] ?? null , 'debug' => APP_ENCRYPT];
            Redis::setex(__FUNCTION__ . ':' . $request['id'] , 86400 , json_encode($sendData));
            dispatch(new SendMsg($sendData))->onQueue('SendMsg');
            START:
            unset($sendData['debug']);
        }

        return $this->responseSuccess($sendData);
    }

    /**
     * 登出
     * User: zmm
     * DateTime: 2023/8/6 15:01
     * @param  Request  $request
     * @return JsonResponse
     */
    public function logout(Request $request) : JsonResponse
    {
        Redis::srem(sprintf(Constant::MEMBERS_DEVICE , $request['uid']) , $request['user_info']['platform']);
        Redis::del(Utils::getMemberKey($request['uid'] , $request['user_info']['platform']));

        // 登出移除标签
        $tagParams['uid'] = $request['uid'];
        $tagParams['type'] = '2';//1-登陆 2-退出
        dispatch(new WorkTag($tagParams))->onQueue('WorkTag');

        return $this->responseSuccess();
    }

    /**
     * desc:清除用户缓存信息
     * author: mxm
     * Time: 2023/8/18   17:34
     */
    public function delMemberCache(){
        Redis::select(1);
        $keys = Redis::keys('member:*');
        foreach ($keys as $key) {
            Redis::del($key);
        }
        Redis::select(0);
    }

    /**
     * desc:获取用户是否在线
     * author: mxm
     * Time: 2023/8/21   16:55
     */
    public function getUserOnline(Request $request): JsonResponse
    {
        $uid = $request->input('id');
        if($uid == ''){
            throw new BadRequestHttpException(__('用户ID为必需'));
        }
        $state = Members::where(['id'=>$uid])->value('state');
        return $this->responseSuccess(['online'=>$state]);
    }

    /**
     * desc:刷用户钱包数据
     * author: mxm
     * Time: 2023/9/14   11:44
     */
    public function userWallet(): JsonResponse
    {
        $idArr = Members::pluck('id')->toArray();
        foreach ($idArr as $val){
            Wallet::registerWallet($val);
        }
        return $this->responseSuccess();
    }
}
