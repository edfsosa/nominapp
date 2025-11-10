<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class AttendanceExportController extends Controller
{
    public function export(AttendanceDay $attendanceDay)
    {
        $pdf = Pdf::loadView('pdf.attendance-day', compact('attendanceDay'));
        return $pdf->stream('attendance_day_' . $attendanceDay->date . '.pdf');
    }
}
