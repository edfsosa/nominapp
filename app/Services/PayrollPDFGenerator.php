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

        $pdf = Pdf::loadView('pdf.payroll', [
            'payroll' => $payroll,
            'companyName' => $settings->company_name,
            'companyRuc' => $settings->company_ruc ?? '',
            'companyAddress' => $settings->company_address ?? '',
            'companyPhone' => $settings->company_phone ?? '',
            'companyEmail' => $settings->company_email ?? '',
            'employerNumber' => $settings->company_employer_number ?? '',
            'city' => $settings->company_city ?? '',
        ])->setPaper('A4');

        $fileName = 'payrolls/recibo_' . $payroll->id . '-' . $payroll->employee->first_name . '_' . $payroll->employee->last_name . '.pdf';

        // Guardar en disco 'public'
        Storage::disk('public')->put($fileName, $pdf->output());

        // Devolver la ruta para guardarla en la BD
        return $fileName;
    }
}
