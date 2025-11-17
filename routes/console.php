<?php

use App\Console\Commands\GenerateAttendanceDays;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(GenerateAttendanceDays::class)->dailyAt('03:00')->withoutOverlapping();
Schedule::command('app:generate-attendance-days')->dailyAt('23:30')->withoutOverlapping();