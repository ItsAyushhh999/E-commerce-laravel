<?php

use App\Jobs\UpdateOrderStatuses;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule order status updates to run daily at 1 AM
Schedule::job(new UpdateOrderStatuses())
    ->dailyAt('01:00')
    ->description('Update order statuses (pending→processing→completed, with random cancellations)');