<?php


namespace App\Http\Middleware;

use Closure;
use App\Tool\Jwt;
use App\Tool\Constant;
use App\Tool\ResponseTrait;
use App\Jobs\AdminHandleLog;
use App\Models\Admin\Admins;
use Illuminate\Http\Request;


class AdminCheckJwt
{
    use ResponseTrait;

    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('Authorization', '');
        if (empty($token) || !str_contains($token, 'Bearer ')) {
            return $this->responseError(Constant::CODE_ARR[Constant::CODE_401], Constant::CODE_401);
            // abort(401);
        }
        [, $token] = explode("Bearer ", $token);
        $token = Jwt::verifyToken($token);
        if (empty($token['uid'])) {
            // abort(401);
            return $this->responseError(Constant::CODE_ARR[Constant::CODE_401], Constant::CODE_401);
        }
        $request->merge([
            'user' => Admins::query()->findOr($token['uid'], ['*'], function () {
                // abort(401);
                return $this->responseError(Constant::CODE_ARR[Constant::CODE_401], Constant::CODE_401);
            })->toArray(),
        ]);
        $response = $next($request);

        $params = [
            'uuid'    => LARAVEL_UUID,
            'uid'     => $token['uid'],
            'method'  => $request->getMethod(),
            'action'  => $request->getPathInfo(),
            'content' => json_encode(['request' => $request->except(['user']), 'response' => json_decode($response->getContent(), 1)], 256),
            'ip'      => $request->getClientIp(),
            'sql'     => '{}',
        ];
        dispatch(new AdminHandleLog($params))->onQueue('adminHandleLog');

        return $response;
    }
}
