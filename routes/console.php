<?php

use App\Modules\Projects\Services\ScheduleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('streams:process-schedules', function () {
    $result = app(ScheduleService::class)->processDueSchedules();

    $this->info(sprintf(
        'Processed due schedules. Started: %d, Failed: %d',
        $result['started'],
        $result['failed'],
    ));
})->purpose('Start any scheduled streams that are due');

Schedule::command('streams:process-schedules')->everyMinute();
