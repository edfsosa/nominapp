<?php

namespace App\Observers;

use App\Models\AttendanceDay;
use App\Services\AttendanceCalculator;

class AttendanceDayObserver
{
    public function saved(AttendanceDay $day)
    {
        AttendanceCalculator::apply($day);
        $day->saveQuietly();
    }
}
