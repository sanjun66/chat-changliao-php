<?php

namespace App\Providers;

use App\Tool\Jwt;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function (Request $request) {
            Route::middleware('api')->prefix('api')->group(base_path('routes/api.php'));
            Route::middleware('web')->group(base_path('routes/web.php'));
            Route::middleware('admin')->prefix('admin')->group(base_path('routes/admin.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('api', function (Request $request) {
            if ($request->header('authorization')) {
                $jwt = explode('Bearer ', $request->header('authorization'))[1];
                $res = Jwt::verifyToken($jwt);
                if (empty($res['uid'])) {
                    $key = $request->ip();
                } else {
                    $key = $res['uid'];
                }
            } else {
                $key = $request->ip();
            }

            return Limit::perMinute(300)->by($key);
        });
    }
}
