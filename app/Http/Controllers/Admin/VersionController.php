<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class VersionController extends Controller
{
    /**
     * 版本信息 列表/查看
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $list = AppVersion::query()
            ->when($request['version_name'], function ($query) use ($request) {
                //
                $query->where('version_name', 'like', "{$request['version_name']}%");
            })
            ->when($request['id'], function ($query) use ($request) {
                //
                $query->where('id', $request['id']);
            })
            ->when($request['platform'], function ($query) use ($request) {
                //
                $query->where('platform', 'like', "{$request['platform']}%");
            })
            ->when($request['date'], function ($query) use ($request) {
                //
                [$startDate, $endDate] = explode(',', $request['date']);
                $query->whereBetween('updated_at', [$startDate, $endDate]);
            })
            ->orderByDesc('id')
            ->paginate(page_size());

        return $this->responseSuccess($list);
    }

    /**
     * 版本信息 添加
     * DateTime: 2023/5/29 10:35
     * @param  Request  $request
     * @return JsonResponse
     */
    public function save(Request $request): JsonResponse
    {
        $params = [
            'platform'  => 'bail|required|string',
            'version_code'      => 'bail|required|string',
            'version_name'      => 'bail|required|string',
            'forced_update'     => 'bail|required|string|in:0,1',
            'update_url'    => 'bail|required|string',
            'desc'        => 'bail|nullable|string'
        ];
        $request->validate($params);
        foreach ($params as $k => $v) {
            if (is_null($request[$k])) {
                unset($params[$k]);
            }
        }
        $data = $request->only(array_keys($params));
        $info = AppVersion::query()->where(['platform'=>$request['platform'],'version_code'=>$request['version_code']])->first();
        if(!$info){
            AppVersion::query()->insert($data);
        }
        return $this->responseSuccess();
    }

    /**
     * 版本信息 删除
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function delete($id, Request $request): JsonResponse
    {
        AppVersion::query()->where('id', $id)->delete();

        return $this->responseSuccess();
    }
}
