<?php

namespace App\Http\Controllers;

use App\Models\Advance;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Genera el comprobante PDF de un adelanto de salario.
 */
class AdvanceController extends Controller
{
    /**
     * Muestra el comprobante PDF del adelanto en el navegador.
     */
    public function show(Advance $advance)
    {
        $advance->load(['employee.activeContract.position.department', 'employee.branch.company', 'approvedBy', 'payroll.period']);

        $settings = app(GeneralSettings::class);

        $company = $advance->employee->company;

        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.advance', [
            'advance' => $advance,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? $settings->company_name,
            'companyRuc' => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone' => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail' => $company?->email ?? $settings->company_email ?? '',
            'employerNumber' => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city' => $company?->city ?? $settings->company_city ?? '',
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("adelanto_{$advance->id}_{$advance->employee->ci}.pdf");
    }
}
