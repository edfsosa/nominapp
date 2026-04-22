<?php

namespace App\Http\Controllers;

use App\Models\Warning;
use App\Settings\GeneralSettings;
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

        $settings = app(GeneralSettings::class);

        $company = $warning->employee->company;

        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.warning', [
            'warning' => $warning,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? $settings->company_name,
            'companyRuc' => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone' => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail' => $company?->email ?? $settings->company_email ?? '',
            'city' => $company?->city ?? $settings->company_city ?? '',
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("amonestacion_{$warning->id}_{$warning->employee->ci}.pdf");
    }
}
