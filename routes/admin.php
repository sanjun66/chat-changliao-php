<?php

use App\Http\Controllers\Admin\PluginController;
use App\Http\Controllers\Admin\VersionController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\GroupController;
use App\Http\Controllers\Admin\FriendController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\AdminController;

Route::group([], function () {
    // 登录
    Route::post('login' , [AdminController::class , 'login']);
    // 检查谷歌
    Route::get('admin/2fa' , [AdminController::class , 'check2fa']);
    // 屏蔽favicon
    Route::any('aave_admin/favicon.ico' , function () {
        return '';
    });



});

Route::group(['middleware'=>'admin.jwt'], function () {

    // 权限列表
    Route::get('permissionList' , [AdminController::class , 'permissionList']);
    // 管理员操作日志
    Route::get('logs' , [AdminController::class , 'logs']);

    // 管理员
    Route::get('' , [AdminController::class , 'adminList']);
    Route::put('' , [AdminController::class , 'adminSave']);
    Route::delete('' , [AdminController::class , 'adminDelete']);
    // 权限管理
    Route::get('authorities' , [AdminController::class , 'authorities']);
    Route::put('authorities' , [AdminController::class , 'authorities']);
    Route::delete('authorities' , [AdminController::class , 'authorities']);
    // 谷歌认证
    Route::post('google2fa' , [AdminController::class , 'google2fa']);
    Route::get('google2fa' , [AdminController::class , 'google2fa']);
    Route::delete('google2fa' , [AdminController::class , 'google2fa']);


    /*===========================用户信息相关===================================================*/
    // 用户列表
    Route::get('user', [MemberController::class, 'list']);
    Route::put('user', [MemberController::class, 'save']);
    Route::delete('user/{id}', [MemberController::class, 'delete'])->whereNumber('id');

    /*===========================好友相关===================================================*/
    // 删除好友
    Route::delete('friends', [FriendController::class, 'delFriends']);
    // 好友列表
    Route::get('friends', [FriendController::class, 'friends']);
    // 搜索好友
    Route::post('searchFriends', [FriendController::class, 'searchFriends']);
    // 好友聊天记录
    Route::get('messageFriends', [FriendController::class, 'messageFriends']);

    /*===========================群组相关===================================================*/
    Route::get('groups', [GroupController::class, 'groups']);
    // 群聊详细信息/修改
    Route::match(['GET', 'PUT'], 'groupInfo', [GroupController::class, 'groupInfo']);
    // 踢出群聊
    Route::post('kickOutGroup', [GroupController::class, 'kickOutGroup']);
    // 解散群聊
    Route::post('dismissGroup', [GroupController::class, 'dismissGroup']);
    // 公告
    Route::match(['POST', 'PUT', 'DELETE', 'GET'], 'noticeGroup', [GroupController::class, 'noticeGroup']);
    // 群禁言
    Route::post('muteMemberGroup', [GroupController::class, 'muteMemberGroup']);
    // 群组聊天记录
    Route::get('messageGroup', [GroupController::class, 'messageGroup']);
    // 设置群管理员
    Route::post('groupManager', [GroupController::class, 'groupManager']);

    /*===========================系统配置相关===================================================*/
    // 获取验证码配置
    Route::get('smsInfo', [SettingController::class, 'smsInfo']);
    // 修改验证码配置
    Route::put('smsSave', [SettingController::class, 'smsSave']);

    // 获取存储配置
    Route::get('ossInfo', [SettingController::class, 'ossInfo']);
    // 修改存储配置
    Route::put('ossSave', [SettingController::class, 'ossSave']);

    // 获取版本配置
    Route::get('versionInfo', [SettingController::class, 'versionInfo']);
    // 修改版本配置
    Route::put('versionSave', [SettingController::class, 'versionSave']);

    // 获取聊天时长配置
    Route::get('getChatSet', [SettingController::class, 'getChatSet']);
    Route::PUT('chatSave', [SettingController::class, 'chatSave']);


    Route::get('roles' , [AdminController::class , 'roleList']);
    Route::delete('roles' , [AdminController::class , 'roleList']);
    Route::put('roles' , [AdminController::class , 'roleList']);
    Route::get('roles/auth' , [AdminController::class , 'roleAuth']);

    /*===========================版本信息相关===================================================*/
    Route::get('Version', [VersionController::class, 'list']);
    Route::put('Version', [VersionController::class, 'save']);
    Route::delete('version/{id}', [VersionController::class, 'delete']);
});
