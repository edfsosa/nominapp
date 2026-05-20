<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use Barryvdh\DomPDF\Facade\Pdf;

class AttendanceExportController extends Controller
{
    public function export(AttendanceDay $attendanceDay)
    {
        // Cargar relaciones necesarias
        $attendanceDay->load([
            'employee.branch.company',
            'employee.activeContract.position.department',
            'events',
        ]);

        $company = $attendanceDay->employee->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.attendance-day', [
            'attendanceDay' => $attendanceDay,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
        ]);

        return $pdf->stream('asistencia_'.$attendanceDay->date->format('Y-m-d').'_'.$attendanceDay->employee->ci.'.pdf');
    }
}
