<?php


namespace App\Tool;

class Constant
{

    // 登录相关操作
    const CODE_401 = 401;
    const CODE_402 = 402;
    const CODE_403 = 403;
    const CODE_ARR = [
        self::CODE_401 => '请先登录，在操作' ,
        self::CODE_402 => '鉴权失败' ,
        self::CODE_403 => '你的设备在其他地方登录' ,
    ];

    const CURRENCY_ARR = [
        'USDT'
    ];

    // 发送消息相关
    const TEXT_MESSAGE = 1;          //文本消息
    const FILE_MESSAGE = 2;          //文件消息
    const FORWARD_MESSAGE = 3;       //转发消息
    const CODE_MESSAGE = 4;          //代码消息
    const VOTE_MESSAGE = 5;          //投票消息
    const GROUP_NOTICE_MESSAGE = 6;  //群组公告
    const FRIEND_APPLY_MESSAGE = 7;  //好友申请
    const USER_LOGIN_MESSAGE = 8;    //登录通知
    const GROUP_INVITE_MESSAGE = 9;  //入群退群设置管理员消息
    const VOICE_MESSAGE = 10; // 语音通过消息
    const VIDEO_MESSAGE = 11;  // 视频通过消息
    const FRIEND_MESSAGE = 12; // 添加好友消息
    const RED_PACKET_MESSAGE = 13; // 红包消息


    const TALK_GROUP = 2;// 群聊
    const TALK_PRIVATE = 1;// 私聊

    const FORWARD_SINGLE = 1; //单条转发
    const FORWARD_MERGE = 2; //合并转发

    // 默认成功
    const SYSTEM_SUCCESS = 200;


    // 错误消息状态码
    const FRIEND_DELETE = 10000;

    // 用户设备
    const MEMBERS_DEVICE = 'members:device:%s';

    //红包相关
    const ORDINARY_PACKET = 1;// 普通红包
    const LUCKY_PACKET = 2; // 拼手气红包
    const SPECIFICITY_PACKET = 3; // 专属红包
}
