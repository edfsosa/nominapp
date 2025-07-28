<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use Carbon\Carbon;

class AttendanceService
{
    // Calcula las horas trabajadas de los empleados por rango de fecha
    public function calculateWorkedHours($employeeId, $startDate, $endDate)
    {
        $attendances = AttendanceEvent::where('employee_id', $employeeId)
            ->whereBetween('recorded_at', [$startDate, $endDate])
            ->get();

        $totalHours = 0;

        foreach ($attendances as $attendance) {
            $clockIn = Carbon::parse($attendance->clock_in);
            $clockOut = Carbon::parse($attendance->clock_out);
            $totalHours += $clockIn->diffInHours($clockOut);
        }

        return $totalHours;
    }
}