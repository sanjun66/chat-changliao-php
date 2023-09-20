<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OptimizeTable extends Command
{

    protected $signature = 'optimize:table';

    protected $description = '定期优化表';


    public function handle()
    {
        $tableNameArr = explode(',' ,
            env('APP_OPTIMIZE_TABLE' , 'friends_apply,friends_groups,groups_member,talk_session'));
        foreach ($tableNameArr as $tableName) {
            DB::selectOne("optimize table `{$tableName}`");
        }
    }
}
