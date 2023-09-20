<?php

namespace App\Console\Commands;

use App\Tool\Aes;
use App\Tool\ResponseTrait;
use GatewayWorker\BusinessWorker;
use GatewayWorker\Gateway;
use GatewayWorker\Register;
use GlobalData\Server;
use Illuminate\Console\Command;
use Workerman\Worker;


class WebsocketServer extends Command
{
    use ResponseTrait;
    protected $signature = 'websocket:server {action} {--daemon}';
    protected $description = 'im即时通讯';

    public function handle()
    {
        $this->runTask();
    }

    public function runTask()
    {
        if (!in_array($action = $this->argument('action') ,
            ['start' , 'stop' , 'restart' , 'status' , 'reload' , 'connections'])) {
            $this->error('Error Arguments');
            exit;
        }
        global $argv;
        $argv[0] = 'gateway-worker:server';
        $argv[1] = $action;
        $argv[2] = $this->option('daemon') ? '-d' : '';
        $this->start();
    }

    private function start()
    {
        self::startGateWay();
        self::startBusinessWorker();
        self::startRegister();
        self::startGlobalData();
        Worker::$logFile = storage_path() .'/'. date('Y-m-d') . '-worker.log';
        Worker::runAll();
    }

    private static function startBusinessWorker()
    {
        $worker                  = new BusinessWorker();
        $worker->name            = config('websocket.worker.name');
        $worker->count           = config('websocket.worker.count');
        $worker->registerAddress = config('websocket.worker.registerAddress');
        $worker->eventHandler    = config('websocket.worker.eventHandler');

    }

    private static function startGateWay()
    {
        $gateway = new Gateway("websocket://0.0.0.0:" . config('websocket.gateway.port'));
        // gateway名称，status方便查看
        $gateway->name = config('websocket.gateway.name');
        // gateway进程数
        $gateway->count = config('websocket.gateway.count');
        // 本机ip，分布式部署时使用内网ip
        $gateway->lanIp = config('websocket.gateway.lanIp');
        // 内部通讯起始端口，假如$gateway->count=4，起始端口为4000
        // 则一般会使用4000 4001 4002 4003 4个端口作为内部通讯端口
        $gateway->startPort            = config('websocket.gateway.startPort');
        $gateway->pingInterval         = config('websocket.gateway.pingInterval');
        $gateway->pingNotResponseLimit = config('websocket.gateway.pingNotResponseLimit');
        // 服务端定时向客户端发送的数据
        $gateway->pingData = (new Aes())->encrypt(json_encode(config('websocket.gateway.pingData')));
        // 服务注册地址
        $gateway->registerAddress = config('websocket.gateway.registerAddress');
    }

    private static function startRegister()
    {
        new Register('text://0.0.0.0:' . config('websocket.register.port'));
    }

    private static function startGlobalData()
    {
        new Server(config('websocket.globalData.ip') , config('websocket.globalData.port'));
    }

}

