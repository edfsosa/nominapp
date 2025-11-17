<?php

namespace App\Console\Commands;

use App\Models\AttendanceDay;
use App\Services\AttendanceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CalculateAttendance extends Command
{
    protected $signature = 'app:calculate-attendance';

    protected $description = 'Calcular horas trabajadas, descansos y registros de asistencia del día';

    public function handle()
    {
        $today = Carbon::today()->toDateString();
        $this->info("🕒 Iniciando cálculo de asistencia para: {$today}");

        $found = false;

        AttendanceDay::with('employee', 'events')
            ->where('date', $today)
            ->chunk(100, function ($days) use (&$found) {
                $found = true;
                foreach ($days as $day) {
                    AttendanceCalculator::apply($day);
                    $day->saveQuietly();
                    $this->line("✅ Procesado ID: {$day->id}");
                }
            });

        if (! $found) {
            $this->warn("⚠️ No se encontraron registros de asistencia para hoy: {$today}");
        } else {
            $this->info("✅ Cálculo de asistencia finalizado correctamente.");
        }

        return Command::SUCCESS;
    }
}
