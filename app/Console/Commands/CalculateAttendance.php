<?php

namespace App\Console\Commands;

use App\Models\AttendanceDay;
use App\Services\AttendanceCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CalculateAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:calculate-attendance';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate total hours, break minutes, and check-in/check-out times for attendance days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today()->toDateString();
        $this->info("Starting attendance calculation for today: {$today}");

        // Buscar solo los registros de hoy
        AttendanceDay::with('employee', 'events')
            ->where('date', $today)
            ->chunk(100, function ($days) {
                foreach ($days as $day) {
                    AttendanceCalculator::apply($day);
                    $day->saveQuietly();
                    $this->info("Processed AttendanceDay ID: {$day->id}");
                }
            });
        
        if (empty(AttendanceDay::where('date', $today)->first())) {
            $this->info("No AttendanceDay records found for today: {$today}");
        }

        $this->info('Attendance calculation completed.');
    }
}
