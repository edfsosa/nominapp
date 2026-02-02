<?php

namespace App\Services;

use App\Models\Payroll;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PayrollPDFGenerator
{
    public function generate(Payroll $payroll): string
    {
        $settings = app(GeneralSettings::class);

        // Intentar obtener datos de la empresa del empleado, si no usar GeneralSettings
        $company = $payroll->employee->company;

        // Obtener ruta del logo (empresa o general)
        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;

        $pdf = Pdf::loadView('pdf.payroll', [
            'payroll' => $payroll,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? $settings->company_name,
            'companyRuc' => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone' => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail' => $company?->email ?? $settings->company_email ?? '',
            'employerNumber' => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city' => $company?->city ?? $settings->company_city ?? '',
        ])->setPaper('A4');

        $fileName = 'payrolls/recibo_' . $payroll->id . '-' . $payroll->employee->first_name . '_' . $payroll->employee->last_name . '.pdf';

        // Guardar en disco 'public'
        Storage::disk('public')->put($fileName, $pdf->output());

        // Devolver la ruta para guardarla en la BD
        return $fileName;
    }
}
