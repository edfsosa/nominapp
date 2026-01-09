<?php

use App\Console\Commands\CalculateAttendance;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Calcular asistencias al final del día
Schedule::command('app:calculate-attendance')->dailyAt('23:30')->withoutOverlapping();

// Verificar ausencias cada 15 minutos (de lunes a sábado, de 6am a 8pm)
Schedule::command('attendance:check-missing')
    ->everyFifteenMinutes()
    ->between('06:00', '20:00')
    ->weekdays()
    ->withoutOverlapping();