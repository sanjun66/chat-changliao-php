<?php

use App\GatewayWorker\Events;
use App\Tool\TalkEventConstant;

return [
    // 中转通知服务
    'http'       => [
        // 监听端口号
        'port'      => env('WEBSOCKET_HTTP_PORT' , 8585) ,
        // 监听地址 本机写127.0.0.1 外网写公网地址 内网写内网地址
        'ip'        => env('WEBSOCKET_HTTP_IP' , '127.0.0.1') ,
        // 合法校验参数
        'checkCode' => env('WEBSOCKET_HTTP_CHECK' , env('APP_KEY')) ,
        // 是否校验
        'checkAuth' => env('WEBSOCKET_HTTP_CHECK_AUTH' , 1) ,
    ] ,
    // 工作进程配置
    'worker'     => [
        // 名字
        'name'            => env('WEBSOCKET_WORKER_NAME' , 'Worker') ,
        // 进程数量
        'count'           => env('WEBSOCKET_WORKER_COUNT' , 32) ,
        // 注册地址
        'registerAddress' => explode(',' , env('WEBSOCKET_REGISTER_ADDRESS' , '127.0.0.1:1236')) ,
        // 所有的逻辑都在这里处理的
        'eventHandler'    => Events::class ,
    ] ,
    // 网关配置
    'gateway'    => [
        // 名字
        'name'                 => env('WEBSOCKET_GATEWAY_NAME' , 'Gateway') ,
        // websocket监听端口
        'port'                 => env('WEBSOCKET_GATEWAY_PORT' , 8282) ,
        // 进程数量
        'count'                => env('WEBSOCKET_GATEWAY_COUNT' , 32) ,
        // 本机ip，分布式部署时使用内网ip
        'lanIp'                => env('WEBSOCKET_GATEWAY_LANIP' , '127.0.0.1') ,
        // 内部通讯起始端口，假如$gateway->count=4，起始端口为2000 则一般会使用2000 2001 2002 2003 4个端口作为内部通讯端口
        'startPort'            => env('WEBSOCKET_GATEWAY_STARTPORT' , 2000) ,
        // 服务端主动发送心跳
        'pingInterval'         => env('WEBSOCKET_GATEWAY_PINGINTERVAL' , 35) ,
        // 0代表服务端允许客户端不发送心跳，服务端不会因为客户端长时间没发送数据而断开连接 1则代表客户端必须定时发送数据给服务端
        'pingNotResponseLimit' => env('WEBSOCKET_GATEWAY_PINGNOTRESPONSELIMIT' , 1) ,
        // 服务端定时向客户端发送的数据
        'pingData'             => env('WEBSOCKET_GATEWAY_PINGDATA' ,
            '{"event_name":"' . TalkEventConstant::EVENT_CLOSE . '","code":200,"message":"","uuid":"","data":null}') ,
        // 服务注册地址
        'registerAddress'      => explode(',' , env('WEBSOCKET_GATEWAY_REGISTERADDRESS' , '127.0.0.1:1236')) ,
    ] ,
    // 注册服务
    'register'   => [
        'port' => env('WEBSOCKET_REGISTER_PORT' , 1236) ,
    ] ,
    // 全局变量共享
    'globalData' => [
        'ip'   => env('WEBSOCKET_GLOBAL_DATA_IP','127.0.0.1') ,
        'port' => env('WEBSOCKET_GLOBAL_DATA_PORT','2207')  ,
    ] ,
];
