<?php

namespace App\Providers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        if (!app()->runningInConsole()) {
            DB::listen(function ($query) {
                if (str_contains(request()->getPathInfo() , '/admin')) {
                    Redis::lpush(LARAVEL_UUID ,
                        vsprintf(str_replace("?" , "'%s'" , $query->sql) , $query->bindings) . ';');
                    Redis::expire(LARAVEL_UUID , mt_rand(3600 , 6000));
                } else if (str_contains(request()->getPathInfo() , '/api')) {
                    Log::channel('web')->info(LARAVEL_UUID . ' ' . $query->time . 'ms ' . vsprintf(str_replace("?" ,
                            "'%s'" ,
                            $query->sql) ,
                            $query->bindings) . ';');
                }
            });
        } else if (app()->environment(['local' , 'test'])) {
            app()->environment(['local' , 'test']) && DB::listen(function ($query) {
                Log::channel('web')->info((LARAVEL_UUID ?? '') . ' ' . $query->time . 'ms ' . vsprintf(str_replace("?" ,
                        "'%s'" ,
                        $query->sql) ,
                        $query->bindings) . ';');
            });
        }
    }
}
