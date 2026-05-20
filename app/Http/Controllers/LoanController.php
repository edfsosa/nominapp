<?php

namespace App\Http\Controllers;

use App\Models\Loan;
use Barryvdh\DomPDF\Facade\Pdf;

class LoanController extends Controller
{
    /**
     * Muestra el PDF del préstamo en el navegador.
     */
    public function show(Loan $loan)
    {
        $loan->load(['employee.activeContract.position.department', 'employee.branch.company', 'grantedBy', 'installments']);

        $company = $loan->employee->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.loan', [
            'loan' => $loan,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("prestamo_{$loan->id}_{$loan->employee->ci}.pdf");
    }
}
