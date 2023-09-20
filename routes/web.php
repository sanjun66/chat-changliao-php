<?php

use Illuminate\Support\Facades\Route;
use zjkal\TimeHelper;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/' , function () {
    return [
        'version' => app()->version() ,
        'date'    => date('Y-m-d H:i:s') ,
        'timestamp'=> TimeHelper::getMilliTimestamp(),
        'uuid'    => LARAVEL_UUID ,
    ];
});
Route::get('/favicon.ico' , function () {
    return '';
});
