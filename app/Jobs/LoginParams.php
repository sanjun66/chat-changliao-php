<?php

namespace App\Jobs;

use App\Models\Groups;
use App\Tool\Utils;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class LoginParams implements ShouldQueue
{
    use Dispatchable , InteractsWithQueue , Queueable , SerializesModels;

    private int $uid;
    private string $registrationId;
    private bool $flag = false;

    public function __construct(int $uid , string $registrationId)
    {
        $this->uid            = $uid;
        $this->registrationId = $registrationId;
    }

    public function handle()
    {
        $this->runTask();
    }

    public function runTask()
    {

        $uid = Redis::get('members:unique:' . $this->registrationId);
        if ($uid == $this->uid) {
            return;
        }
        if (Redis::exists('members:unique:' . $this->registrationId)) {
            Redis::srem('members:registration:' . $uid , $this->registrationId);
            $this->checkReplaceDevice();
        }
        $this->checkNewDevice();
        Redis::sadd('members:registration:' . $this->uid , $this->registrationId);
        Redis::set('members:unique:' . $this->registrationId , $this->uid);

    }

    public function checkNewDevice()
    {
        $idArr = Groups::query()->from('groups' , 'g')->join('groups_member as m' , 'g.id' , '=' ,
            'm.group_id')->where([
            'm.uid'        => $this->uid ,
            'g.is_dismiss' => 0 ,
        ])->get([
            'g.id' ,
        ])->pluck('id')->toArray();
        foreach ($idArr as $groupId) {
            Utils::addNewDeviceTag($groupId , $this->registrationId);
        }
    }

    public function checkReplaceDevice()
    {
        Utils::removeDeviceTags($this->registrationId , Utils::getDeviceTag($this->registrationId));
    }
}
