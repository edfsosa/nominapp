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
            'employee.branch.company',
            'employee.activeContract.position.department',
            'events'
        ]);

        $settings = app(GeneralSettings::class);

        // Obtener datos de la empresa del empleado, si no usar GeneralSettings
        $company = $attendanceDay->employee->company;

        // Obtener ruta del logo (empresa o general)
        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;

        // Generar PDF con vista y datos cargados
        $pdf = Pdf::loadView('pdf.attendance-day', [
            'attendanceDay' => $attendanceDay,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? $settings->company_name,
            'companyRuc' => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone' => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail' => $company?->email ?? $settings->company_email ?? '',
            'employerNumber' => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city' => $company?->city ?? $settings->company_city ?? '',
        ]);

        return $pdf->stream('asistencia_' . $attendanceDay->date->format('Y-m-d') . '_' . $attendanceDay->employee->ci . '.pdf');
    }
}
