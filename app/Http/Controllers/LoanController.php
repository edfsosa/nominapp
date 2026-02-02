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
        $loan->load(['employee.position.department', 'grantedBy', 'installments']);

        $settings = app(GeneralSettings::class);

        $pdf = Pdf::loadView('pdf.loan', [
            'loan' => $loan,
            'companyName' => $settings->company_name,
            'companyRuc' => $settings->company_ruc ?? '',
            'companyAddress' => $settings->company_address ?? '',
            'companyPhone' => $settings->company_phone ?? '',
            'companyEmail' => $settings->company_email ?? '',
            'employerNumber' => $settings->company_employer_number ?? '',
            'city' => $settings->company_city ?? '',
        ])->setPaper('a4', 'portrait');

        $type = $loan->isLoan() ? 'prestamo' : 'adelanto';

        return $pdf->stream("{$type}_{$loan->id}_{$loan->employee->ci}.pdf");
    }
}
