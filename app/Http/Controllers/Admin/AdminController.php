<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Admins;
use App\Models\Admin\Authorities;
use App\Models\Admin\HandleLog;
use App\Models\Admin\Roles;
use App\Models\Admin\RolesAuthorities;
use App\Tool\GoogleAuth;
use App\Tool\Jwt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;


class AdminController extends Controller
{

    public function permissionList(Request $request) : JsonResponse
    {
        $authList = Authorities::authList();
        if (!empty($request['user']['role_id'])) {
            $roleAuthList = RolesAuthorities::query()->whereIn('role_id' ,
                explode(',' , $request['user']['role_id']))->get(['auth_id'])->toArray();
            $authIdArr    = array_unique(array_column($roleAuthList , 'auth_id'));
            foreach ($authList as $k => $v) {
                if (!in_array($v['id'] , $authIdArr)) {
                    unset($authList[$k]);
                }
            }
            $authList = array_values($authList);
        }

        return $this->responseSuccess($authList);

    }


    public function login(Request $request) : JsonResponse
    {
        $request->validate([
            'admin_name' => 'bail|required|string|max:32|exists:admins,admin_name' ,
            'password'   => 'bail|required|string|min:6' ,
            'code'       => 'bail|nullable|size:6' ,
        ]);
        $model = Admins::query()->where('admin_name' , $request['admin_name'])->first();
        if (!$model) {
            throw new BadRequestHttpException("账号不存在");
        }
        if (!Hash::check($request['password'] , $model['password'])) {
            throw new BadRequestHttpException("密码错误");
        }
        if ($model['state']) {
            throw new BadRequestHttpException("账户已冻结，请联系超级管理员");
        }
        if ($model['secret'] && !GoogleAuth::verify_key($model['secret'] , $request['code'])) {
            throw new BadRequestHttpException("谷歌验证码错误");
        }

        $model->login_date = date('Y-m-d H:i:s');
        $model->save();
        $token = Jwt::getToken($model['id']);
        $ip    = $request->getClientIp();
        DB::table('admin_login_log')->insert(['uid' => $model['id'] , 'ip' => $ip]);

        return $this->responseSuccess([
            'token'      => $token ,
            'login_ip'   => $ip ,
            'token_type' => 'Bearer' ,
        ]);
    }

    public function logs(Request $request) : JsonResponse
    {
        $list = HandleLog::query()->from('admin_handle_log' , 'r')->leftJoin('admins as s' , 'r.uid' ,
            '=' ,
            's.id')->when($request['uid'] , function ($query) use ($request) {
            $query->where('r.uid' , $request['uid']);
        })->when($request['date'] , function ($query) use ($request) {
            [$startDate , $endDate] = explode(',' , $request['date']);
            $query->where('r.created_at' , '<=' , $endDate)->where('r.created_at' , '>=' , $startDate);
        })->when($request['ip'] , function ($query) use ($request) {
            $query->where('r.ip' , $request['ip']);
        })->orderByDesc('r.id')->paginate(page_size() , [
            'r.id' ,
            'r.uid' ,
            'r.method' ,
            'r.action' ,
            'r.content' ,
            'r.ip' ,
            'r.created_at' ,
            's.admin_name' ,
        ])->toArray();
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['content'] = $v['content']['request'] ?? [];
        }

        return $this->responseSuccess($list);
    }

    public function adminList(Request $request) : JsonResponse
    {
        $list = Admins::query()->from('admins' , 'r')->when($request['phone'] , function ($query) use ($request) {
            $query->where('r.phone' , $request['phone']);
        })->when($request['email'] , function ($query) use ($request) {
            $query->where('r.email' , $request['email']);
        })->when($request['state'] > -1 , function ($query) use ($request) {
            $query->where('r.state' , $request['state']);
        })->when($request['login_date'] , function ($query) use ($request) {
            [$startDate , $endDate] = explode(',' , $request['login_date']);
            $query->where('r.login_date' , '<=' , $endDate)->where('r.login_date' , '>=' , $startDate);
        })->orderByDesc('r.id')->paginate(page_size() , ['r.*'])->toArray();
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['login_count'] = DB::table('admin_login_log')->where('uid' , $v['id'])->count();
            $list['data'][$k]['google_auth'] = (bool) $v['secret'];
            unset($list['data'][$k]['secret']);
        }

        return $this->responseSuccess($list);
    }

    public function adminSave(Request $request) : JsonResponse
    {
        $params = [
            'id'         => 'bail|nullable|exists:admins,id' ,
            'password'   => 'bail|nullable|alpha_dash|min:6|max:20' ,
            'admin_name' => 'bail|nullable|string|max:32' ,
            'phone'      => 'bail|nullable|string|max:20' ,
            'email'      => 'bail|nullable|email|max:128' ,
            'desc'       => 'bail|nullable|string|max:1000' ,
            'role_id'    => 'bail|nullable|string|max:1024' ,
            'state'      => 'bail|nullable|in:0,1' ,
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
        if (isset($data['admin_name']) && empty($request['id'])) {
            if (Admins::query()->where('admin_name' , $data['admin_name'])->value('admin_name')) {
                throw new BadRequestHttpException("用户名重复，请更换用户名，在尝试");
            }
        }

        Admins::query()->updateOrCreate(['id' => $request['id']] , $data);

        return $this->responseSuccess();
    }

    public function adminDelete(Request $request) : JsonResponse
    {
        if ($request['id'] == $request['user']['id']) {
            throw new BadRequestHttpException("不能删除当前登录账户");
        }
        Admins::query()->where('id' , $request['id'])->delete();

        return $this->responseSuccess();
    }


    public function authorities(Request $request) : JsonResponse
    {
        $method = $request->getMethod();
        if ('GET' == $method) {
            $array = Authorities::authList();

            return $this->responseSuccess(['auth_list' => $array]);
        } else if ('DELETE' == $method) {
            $object = Authorities::query()->where('id' , $request['id'])->first();
            if (!in_array($object['type'] , [2 , 3])) {
                if (Authorities::query()->where('parent_id' , $request['id'])->exists()) {
                    throw new BadRequestHttpException("请先删除菜单，在删除目录");
                }
            }

            $object && $object->delete();


            return $this->responseSuccess();
        }
        $params = [
            'id'      => 'bail|nullable|exists:authorities,id' ,
            'title'   => 'bail|required|string|max:128' ,
            'alias'   => 'bail|required|string|max:128' ,
            'action'  => 'bail|nullable|string|max:128' ,
            'sort'    => 'bail|required|int|min:0' ,
            'type'    => 'bail|required|in:1,2,3' ,
            'is_show' => 'bail|required|in:0,1' ,
        ];
        $request->validate($params);
        if ($request['parent_id'] && !Authorities::query()->where('id' ,
                $request['parent_id'])->exists()) {
            throw new BadRequestHttpException("添加权限失败");
        }
        if ($request['id']) {
            $object = Authorities::query()->find($request['id']);
            if (!$object) {
                throw new BadRequestHttpException("权限不存在，修改失败");
            }
            if ($object['type'] == 1 && $request['type'] != 1 && Authorities::query()->where('parent_id' ,
                    $request['id'])->exists()) {
                throw new BadRequestHttpException("不能修改权限的类型，权限目录下面有菜单");
            }
        }

        $data['action']    = '';
        $data['parent_id'] = intval($request['parent_id']);
        $request['id'] && $request['type'] == 1 && $data['parent_id'] = 0;

        Authorities::query()->updateOrCreate(['id' => $request['id']] ,
            array_merge($request->only(array_keys($params)) , $data));

        return $this->responseSuccess();
    }

    public function google2fa(Request $request) : JsonResponse
    {
        $method = $request->method();
        if ('GET' == $method) {
            $secret = GoogleAuth::generate_secret_key();

            return $this->responseSuccess([
                'google_auth' => (bool) $request['user']['secret'] ,
                'secret'      => $secret ,
                'qr_code'     => 'otpauth://totp/' . env('APP_NAME') . ':' . $request['user']['id'] . '?secret=' . $secret . '&issuer=' . $request['user']['admin_name'] ,
            ]);

        } else if ('POST' == $method) {
            $request->validate(['secret' => 'bail|required|string|size:16' , 'code' => 'bail|required|size:6']);
            if (!GoogleAuth::verify_key($request['secret'] , $request['code'])) {
                throw new BadRequestHttpException("验证码错误");
            }
            $secret = $request['secret'];
        } else if ('DELETE' == $method) {
            if (!GoogleAuth::verify_key($request['user']['secret'] , $request['code'])) {
                throw new BadRequestHttpException("验证码错误");
            }
        }

        Admins::query()->where('id' , $request['user']['id'])->update(['secret' => $secret ?? '']);

        return $this->responseSuccess();

    }

    public function check2fa(Request $request) : JsonResponse
    {
        return $this->responseSuccess([
            'google_auth' => (bool) Admins::query()->where('admin_name' , $request['admin_name'])->value('secret') ,
        ]);
    }

    public function roleAuth(Request $request) : JsonResponse
    {
        $array    = Authorities::authList();
        $authList = auth_list($array);
        $tmpArr   = array_column(RolesAuthorities::query()->whereIn('role_id' ,
            explode(',' , $request['id']))->get(['auth_id'])->toArray() ,
            'auth_id');
        $tmpArr   = array_unique($tmpArr);
        foreach ($authList as $k => $v) {
            $authList[$k]['selected'] = in_array($v['id'] , $tmpArr);
        }

        return $this->responseSuccess(['role_auth_list' => $authList]);
    }

    public function roleList(Request $request) : JsonResponse
    {
        $method = $request->getMethod();
        if ('GET' == $method) {
            $roleAuthList = RolesAuthorities::query()->get(['role_id' , 'auth_id'])->toArray();
            $authList     = [];
            foreach ($roleAuthList as $k => $v) {
                $authList[$v['role_id']][] = $v['auth_id'];
            }

            return $this->responseSuccess([
                'role_list' => Roles::query()->when($request['state'] > -1 , function ($query) use ($request) {
                    $query->where('state' , intval($request['state']));
                })->get()->each(function ($val) use ($authList) {
                    $val['auth_id'] = join(',' , empty($authList[$val['id']]) ? [0] : $authList[$val['id']]);
                })->toArray() ,
            ]);
        } else if ('DELETE' == $method) {
            Roles::query()->where('id' , $request['id'])->delete();
            RolesAuthorities::query()->where('role_id' , $request['id'])->delete();

            return $this->responseSuccess();
        } else if ('PUT' == $method) {
            $params = [
                'name'    => 'bail|required|string|max:128' ,
                'desc'    => 'bail|required|string|max:128' ,
                'id'      => 'bail|nullable|int|exists:roles,id' ,
                'auth_id' => 'bail|nullable|string' ,
                'state'   => 'bail|required|in:0,1' ,
            ];
            $request->validate($params);
            unset($params['auth_id']);
            DB::beginTransaction();
            $object = Roles::query()->updateOrCreate(['id' => $request['id']] ,
                $request->only(array_keys($params)));
            if ($request['auth_id']) {
                $result = [];
                foreach (explode(',' , $request['auth_id']) as $v) {
                    $result[] = ['role_id' => $object['id'] , 'auth_id' => $v];
                }
                RolesAuthorities::query()->where(['role_id' => $object['id']])->delete();
                RolesAuthorities::query()->insert($result);
            }
            DB::commit();

            return $this->responseSuccess();
        }

        return $this->responseSuccess();
    }
}
