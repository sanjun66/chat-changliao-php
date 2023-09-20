<?php

namespace App\Jobs;

use App\Models\Admin\HandleLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class AdminHandleLog implements ShouldQueue
{
    use Dispatchable , InteractsWithQueue , Queueable , SerializesModels;


    private array $params;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(array $params = [])
    {
        $this->params = $params;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        HandleLog::query()->insert(array_merge($this->params ,
            ['sql' => json_encode(Redis::lrange($this->params['uuid'] , 0 , -1) , 256)]));
        Redis::del($this->params['uuid']);
    }
}
