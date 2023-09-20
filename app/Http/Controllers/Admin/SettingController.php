<?php


namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class SettingController extends Controller
{
    /**
     * 获取验证码配置
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function smsInfo(Request $request): JsonResponse
    {
        // 
        $data = Setting::whereIn('type', ['sms', 'dxbsms'])->get(['id', 'key', 'value', 'type', 'edit'])->groupBy('type');
        return $this->responseSuccess($data);
    }

    private static function smsInfoHandle($request): array
    {
        $params = [
            "dxb_username" => 'bail|nullable|string',
            "dxb_key" => 'bail|nullable|string',
            "sms_type" => 'bail|required|int|in:1,2',
            "sms_status"   => 'bail|required|int|in:1,2',
            "email_status"      => 'bail|required|int|in:1,2',
        ];
        $request->validate($params);

        if ($request['sms_type'] == 2) {
            // 
            $request->validate([
                "dxb_username" => 'required',
                "dxb_key" => 'required',
            ]);
        }

        foreach ($params as $k => $v) {
            if (is_null($request[$k])) {
                unset($params[$k]);
            }
        }

        return array_keys($params);
    }

    /**
     * 修改验证码配置
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function smsSave(Request $request): JsonResponse
    {
        // 
        $params = self::smsInfoHandle($request);
        $result = $request->only($params);
        foreach ($result as $k => $v) {
            // 
            Setting::where('key', $k)->update(['value' => $v]);
        }
        return $this->responseSuccess();
    }

    /**
     * desc:oss配置
     * author: mxm
     * Time: 2023/8/15   18:25
     * @param  Request  $request
     * @return JsonResponse
     */
    public function ossInfo(Request $request): JsonResponse
    {
        $data = Setting::whereIn('type', ['oss', 'aws'])->get(['id', 'key', 'value', 'type', 'edit'])->groupBy('type');
        return $this->responseSuccess($data);
    }

    /**
     * 修改oss配置
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function ossSave(Request $request): JsonResponse
    {
        $params = self::ossInfoHandle($request);
        $result = $request->only($params);
        foreach ($result as $k => $v) {
            //
            Setting::where('key', $k)->update(['value' => $v]);
        }
        return $this->responseSuccess();
    }

    private static function ossInfoHandle($request): array
    {
        $params = [
            "oss_status"=>'bail|required|string',
            "aws_access_key_id" => 'bail|nullable|string',
            "aws_secret_access_key" => 'bail|nullable|string',
            "aws_default_region" => 'bail|nullable|string',
            "aws_bucket"   => 'bail|nullable|string',
            "aws_url"      => 'bail|nullable|string',
        ];
        $request->validate($params);

        if ($request['oss_status'] == 2) {
            //
            $request->validate([
                "aws_access_key_id" => 'required',
                "aws_secret_access_key" => 'required',
                "aws_default_region" => 'required',
                "aws_bucket" => 'required',
                "aws_url" => 'required',
            ]);
        }

        foreach ($params as $k => $v) {
            if (is_null($request[$k])) {
                unset($params[$k]);
            }
        }

        return array_keys($params);
    }

    /**
     * desc:获取版本配置
     * author: mxm
     * Time: 2023/8/29   10:48
     * @return JsonResponse
     */
    public function versionInfo(): JsonResponse
    {
        $data = Setting::where('type', 'version')->get(['id', 'key', 'value', 'type', 'edit'])->toArray();
        return $this->responseSuccess($data);
    }

    /**
     * desc:修改版本配置
     * author: mxm
     * Time: 2023/8/29   10:48
     * @param  Request  $request
     * @return JsonResponse
     */
    public function versionSave(Request $request): JsonResponse
    {
        $params = self::versionHandle($request);
        $result = $request->only($params);
        foreach ($result as $k => $v) {
            //
            Setting::where('key', $k)->update(['value' => $v]);
        }
        return $this->responseSuccess();
    }

    private static function versionHandle($request): array
    {
        $params = [
            "android_version"=>'bail|string',
            "ios_version" => 'bail|string',
            "desc" => 'bail|string',
            "force_update" => 'bail|required|string',
        ];
        $request->validate($params);

        if ($request['force_update'] == 1) {
            //开启强制更新
            $request->validate([
                "android_version" => 'required',
                "ios_version" => 'required',
                "desc" => 'required',
            ]);
        }

        foreach ($params as $k => $v) {
            if (is_null($request[$k])) {
                unset($params[$k]);
            }
        }

        return array_keys($params);
    }

    /**
     * desc:获取聊天的配置
     * author: mxm
     * Time: 2023/9/14   17:25
     */
    public function  getChatSet(Request $request): JsonResponse
    {
        $data = Setting::whereIn('type', ['chat'])->get(['id', 'key', 'value', 'type', 'edit'])->groupBy('type');
        return $this->responseSuccess($data);
    }

    /**
     * desc:修改聊天保存时长
     * author: mxm
     * Time: 2023/9/14   17:29
     */
    public function  chatSave(Request $request): JsonResponse
    {
        $params = [
            "value"=>'bail|required|string',
        ];
        $request->validate($params);
        Setting::query()->where('key','=','retention_time')->update(['value'=>$request['value']]);
        return $this->responseSuccess();

    }



}
