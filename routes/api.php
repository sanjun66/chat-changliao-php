<?php

use App\Http\Controllers\Api\TalkController;
use App\Tool\Aes;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1Controller;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\FriendController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\MessageController;
use Illuminate\Http\Request;



Route::group(['middleware' => 'jwt'], function () {
    /*===========================用户信息相关===================================================*/
    // 登录
    Route::post('login', [MemberController::class, 'login']);
    // 退出登录
    Route::post('logout', [MemberController::class, 'logout']);
    // 令牌刷新接口
    Route::post('refreshToken', [MemberController::class, 'refreshToken']);
    // 用户信息查看修改
    Route::match(['POST','GET', 'PUT'], 'userInfo', [MemberController::class, 'userInfo']);
    // 更换用户头像
    Route::post('modifyAvatar', [MemberController::class, 'modifyAvatar']);
    // 新更换用户头像
    Route::post('modifyAvatarNew', [MemberController::class, 'modifyAvatarNew']);
    // 修改密码
    Route::post('modifyPassword', [MemberController::class, 'modifyPassword']);
    // 忘记密码
    Route::post('forgetPassword', [MemberController::class, 'forgetPassword']);
    // 上传文件
    Route::post('uploadFile', [MemberController::class, 'uploadFile']);
    // 检测聊天消息
    Route::post('checkParams',[MemberController::class, 'checkParams']);
    // 更新状态
    Route::post('talkState',[MemberController::class, 'talkState']);
    // 清除用户缓存信息
    Route::get('delMemberCache',[MemberController::class, 'delMemberCache']);
    // 获取用户在线状态
    Route::post('getUserOnline',[MemberController::class, 'getUserOnline']);
    // 版本更新接口
    Route::post('getVersionInfo',[V1Controller::class, 'getVersionInfo']);
    /*===========================好友相关===================================================*/
    // 删除好友
    Route::delete('friends', [FriendController::class, 'delFriends']);
    // 删除好友安卓
    Route::post('friendsDel', [FriendController::class, 'delAndroidFriends']);
    // 好友列表
    Route::match(['POST','GET'],'friends', [FriendController::class, 'friends']);
    // 搜索好友
    Route::post('searchFriends', [FriendController::class, 'searchFriends']);
    // 好友申请
    Route::post('friendsApply', [FriendController::class, 'friendsApply']);
    // 获取申请好友列表
    Route::post('applyList', [FriendController::class, 'applyList']);
    // 审核申请
    Route::post('checkApply', [FriendController::class, 'checkApply']);
    // 创建分组 删除分组 修改分组
    Route::match(['POST','DELETE','PUT'] ,'friendGroup' ,[FriendController::class , 'friendGroup']);
    // 好友修改到分组里面
    Route::post('moveFriendGroup',[FriendController::class , 'moveFriendGroup']);
    // 加入黑名单
    Route::match(['GET','POST','PUT'] ,'friendBlack' ,[FriendController::class , 'friendBlack']);
    // 黑名单列表
    Route::post('friendBlackList' ,[FriendController::class , 'friendBlackList']);
    // 设置备注
    Route::post('friendNotes' ,[FriendController::class , 'friendNotes']);
    // 免打扰
    Route::post('friendDisturb' ,[FriendController::class , 'friendDisturb']);


    /*===========================群聊相关===================================================*/
    // 创建群聊
    Route::post('createGroup', [GroupController::class, 'createGroup']);
    // 邀请加入群聊
    Route::post('inviteGroup', [GroupController::class, 'inviteGroup']);
    // 群信息查看 修改
    Route::match(['GET','PUT','POST'],'groupInfo', [GroupController::class, 'groupInfo']);
    // 踢出群聊
    Route::post('kickOutGroup', [GroupController::class, 'kickOutGroup']);
    // 解散群聊
    Route::post('dismissGroup' , [GroupController::class , 'dismissGroup']);
    // 公告
    Route::match(['POST','PUT','DELETE'],'noticeGroup' ,[GroupController::class , 'noticeGroup']);
    // 群禁言
    Route::post('muteMemberGroup' ,[GroupController::class , 'muteMemberGroup']);
    // 自动退群
    Route::post('exitGroup' ,[GroupController::class , 'exitGroup']);
    // 群里列表
    Route::match(['GET','POST'],'groupList',[GroupController::class , 'groupList']);
    // 免打扰
    Route::post('groupDisturb' ,[GroupController::class , 'groupDisturb']);
    // 设置管理员
    Route::post('groupManager' ,[GroupController::class , 'groupManager']);
    // 扫码进群
    Route::post('scanGroup' ,[GroupController::class , 'scanGroup']);

    /*============================聊天相关==================================================*/
    // 消息撤回
    Route::post('msgRevoke' , [MessageController::class , 'msgRevoke']);
    // 消息转发
    Route::post('msgForward' , [MessageController::class , 'msgForward']);
    // 消息发送
    Route::post('msgSend' , [MessageController::class , 'msgSend']);
    // 通过密码获取消息
    Route::post('msgDecrypt',[MessageController::class , 'msgDecrypt']);
    // 发送验证码
    Route::post('sms',  [V1Controller::class, 'sendCode']);
    // ucloud
    Route::post('ucloud', [V1Controller::class, 'ucloud']);
    // 手机区号
    Route::post('areaCode', [V1Controller::class, 'areaCode']);
    // 获取存储配置信息
    Route::post('getOssInfo', [V1Controller::class, 'getOssInfo']);
    // 上传文件
    Route::post('localUpload', [V1Controller::class, 'localUpload']);
    // 查找聊天记录
    Route::post('getHistoryList' , [MessageController::class , 'getHistoryList']);
    // 消息已读
    Route::post('setMessageRead' , [MessageController::class , 'setMessageRead']);
    // 已读未读明细
    Route::post('getReadList' , [MessageController::class , 'getReadList']);
    Route::post('sendTest' , [V1Controller::class , 'sendTest']);
    // 消息回执
    Route::post('msgReceipt' , [MessageController::class , 'msgReceipt']);

    /*============================历史消息相关==================================================*/
    // 聊天对话框
    Route::post('getTalkList',[TalkController::class, 'talkList']);
    // 拉取对话消息
    Route::post('getChatMessage',[TalkController::class, 'getChatMessage']);
    // 删除会话
    Route::post('delMeeting',[TalkController::class, 'delMeeting']);
    // 离线消息
    Route::post('talkPull',[TalkController::class, 'talk_pull']);
    // 离线已读消息
    Route::post('talkRead',[TalkController::class, 'talk_read']);
    // 删除消息
    Route::post('delMsg',[TalkController::class, 'delMsg']);

    Route::post('userWallet',[MemberController::class, 'userWallet']);


});

Route::post('jiemi',function (Request $request){
    $tmpArr = json_decode((new Aes())->decrypt($request->input('str')) , 1);
    dd($tmpArr);
});

Route::post('jiami',function (Request $request){
    $tmpArr = json_decode((new Aes())->encrypt(['id'=>79,'talk_type'=>1,'messageId'=>0]) , 1);
    dd($tmpArr);
});

Route::get('trySendMsg',[V1Controller::class, 'trySendMsg']);




