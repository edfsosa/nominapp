<?php

namespace App\Http\Controllers;

use App\Models\AttendanceDay;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;

class AttendanceExportController extends Controller
{
    public function export(AttendanceDay $attendanceDay)
    {
        // Cargar relaciones necesarias
        $attendanceDay->load([
            'employee.branch',
            'employee.position.department',
            'events'
        ]);

        $settings = app(GeneralSettings::class);

        // Generar PDF con vista y datos cargados
        $pdf = Pdf::loadView('pdf.attendance-day', [
            'attendanceDay' => $attendanceDay,
            'companyName' => $settings->company_name,
            'companyRuc' => $settings->company_ruc ?? '',
            'companyAddress' => $settings->company_address ?? '',
            'companyPhone' => $settings->company_phone ?? '',
            'companyEmail' => $settings->company_email ?? '',
            'employerNumber' => $settings->company_employer_number ?? '',
            'city' => $settings->company_city ?? '',
        ]);

        return $pdf->stream('asistencia_' . $attendanceDay->date->format('Y-m-d') . '_' . $attendanceDay->employee->ci . '.pdf');
    }
}
