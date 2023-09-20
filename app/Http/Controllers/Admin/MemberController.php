<?php


namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Members;
use App\Tool\Utils;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class MemberController extends Controller
{
    /**
     * 用户信息 列表/查看
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        // 
        $state = $request['state'] ?? 'false';
        if ($state == 0) {
            // 
            $state = '离线';
        }
        if ($state == 1) {
            // 
            $state = '在线';
        }
        $memberInfo = Members::query()
            ->when($request['nick_name'], function ($query) use ($request) {
                // 
                $query->where('nick_name', 'like', "{$request['nick_name']}%");
            })
            ->when($request['id'], function ($query) use ($request) {
                // 
                $query->where('id', $request['id']);
            })
            ->when($state, function ($query) use ($state) {
                // 
                if ($state == '离线') {
                    $query->where('state', 0);
                }
                if ($state == '在线') {
                    $query->where('state', 1);
                }
            })
            ->when($request['phone'], function ($query) use ($request) {
                // 
                $query->where('phone', 'like', "{$request['phone']}%");
            })
            ->when($request['email'], function ($query) use ($request) {
                // 
                $query->where('email', 'like', "{$request['email']}%");
            })
            ->when($request['date'], function ($query) use ($request) {
                // 
                [$startDate, $endDate] = explode(',', $request['date']);
                $query->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->orderByDesc('id')
            ->paginate(page_size(), [
                'id', 'nick_name', 'avatar', 'account', 'sex', 'age', 'state', 'phone', 'email', 'created_at','driver'
            ]);
            foreach ($memberInfo as $key =>$val){
                $memberInfo[$key]['avatar'] = Utils::getAvatarUrl($val['avatar'],$val['driver']);
            }
        return $this->responseSuccess($memberInfo);
    }

    /**
     * 用户信息 修改/添加
     * User: zmm
     * DateTime: 2023/5/29 10:35
     * @param  Request  $request
     * @return JsonResponse
     */
    public function save(Request $request): JsonResponse
    {
        $params = [
            'id'         => 'bail|nullable|exists:members,id',
            'nick_name'  => 'bail|nullable|string|max:20',
            'phone'      => 'bail|nullable|int|unique:members,phone,' . $request['id'],
            'email'      => 'bail|nullable|email|max:128|unique:members,email,' . $request['id'],
            'avatar'     => 'bail|nullable|string|max:128',
            'account'    => 'bail|nullable|string|max:16|unique:members,account,' . $request['id'],
            'sex'        => 'bail|nullable|int|in:0,1,2',
            'age'        => 'bail|nullable|int|max:65535|min:0',
            'address'    => 'bail|nullable|string|max:128',
            'password'   => ['bail', 'nullable', 'string', Password::min(6)->letters()->numbers()],
            'sign'       => 'bail|nullable|string|max:128',
        ];
        $request->validate($params);
        foreach ($params as $k => $v) {
            if (is_null($request[$k])) {
                unset($params[$k]);
            }
        }
        $data = $request->only(array_keys($params));

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // 添加时候的判断
        $exists = Members::query()->where('id', $request['id'])->exists();
        if ($exists === false) {
            $params = [
                'phone'   => 'bail|required|int|unique:members,phone',
                'email'   => 'bail|required|email|max:128|unique:members,email',
            ];
            $request->validate($params);

            // 添加无设置密码默认密码
            if (!isset($data['password'])) {
                $data['password'] = Hash::make('123456');
            }
            // 添加无设置昵称默认昵称
            if (!isset($data['nick_name'])) {
                $data['nick_name'] = Utils::getNickName();
            }
            // 添加无设置性别默认性别
            if (!isset($data['sex'])) {
                $data['sex'] = mt_rand(0, 2);
            }
            // 添加无设置年龄默认年龄
            if (!isset($data['age'])) {
                $data['age'] = mt_rand(15, 50);
            }
            // 添加无设置添加好友状态默认添加好友状态
            if (!isset($data['apply_auth'])) {
                $data['apply_auth'] = 0;
            }
        }

        $model = Members::query()->updateOrCreate(['id' => $request['id']], $data);
        $model->setAttribute('account', Utils::enCode($model['id']));
        $model->save();
        return $this->responseSuccess();
    }

    /**
     * 用户信息 删除
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function delete($id, Request $request): JsonResponse
    {
        Members::query()->where('id', $id)->delete();

        return $this->responseSuccess();
    }
}
