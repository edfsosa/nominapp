<?php

namespace App\Http\Controllers;

use App\Models\Warning;
use Barryvdh\DomPDF\Facade\Pdf;

/** Genera el documento PDF de una amonestación laboral. */
class WarningController extends Controller
{
    /**
     * Muestra el documento PDF de la amonestación en el navegador.
     */
    public function show(Warning $warning): mixed
    {
        $warning->load(['employee.activeContract.position.department', 'employee.branch.company', 'issuedBy']);

        $company = $warning->employee->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.warning', [
            'warning' => $warning,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'city' => $company?->city ?? '',
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("amonestacion_{$warning->id}_{$warning->employee->ci}.pdf");
    }
}
