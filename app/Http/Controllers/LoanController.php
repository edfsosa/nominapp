<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;

class LoanController extends Controller
{
    /**
     * Muestra el PDF del préstamo/adelanto en el navegador.
     */
    public function show(Loan $loan)
    {
        $loan->load(['employee.position.department', 'employee.branch.company', 'grantedBy', 'installments']);

        $settings = app(GeneralSettings::class);

        // Obtener datos de la empresa del empleado, si no usar GeneralSettings
        $company = $loan->employee->company;

        // Obtener ruta del logo (empresa o general)
        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;

        $pdf = Pdf::loadView('pdf.loan', [
            'loan' => $loan,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? $settings->company_name,
            'companyRuc' => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone' => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail' => $company?->email ?? $settings->company_email ?? '',
            'employerNumber' => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city' => $company?->city ?? $settings->company_city ?? '',
        ])->setPaper('a4', 'portrait');

        $type = $loan->isLoan() ? 'prestamo' : 'adelanto';

        return $pdf->stream("{$type}_{$loan->id}_{$loan->employee->ci}.pdf");
    }
}
