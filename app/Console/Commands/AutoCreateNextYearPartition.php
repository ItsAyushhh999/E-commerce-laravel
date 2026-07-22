<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Signature('db:create-yearly-partition {table}')]
#[Description('Yearly Partition Creation')]
class AutoCreateNextYearPartition extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = $this->argument('table');
        $thisYear = Carbon::now()->year;
        $nextYear = $thisYear + 1;

        DB::unprepared("
            ALTER TABLE {$table} REORGANIZE PARTITION p_future INTO (
            PARTITION p{$thisYear} VALUES LESS THAN ({$nextYear}),
            PARTITION p_future VALUES LESS THAN MAXVALUE
            )
        ");

        $this->info("Partition p{$thisYear} added to {$table}.");
    }
}
