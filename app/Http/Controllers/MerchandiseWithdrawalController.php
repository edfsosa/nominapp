<?php

namespace App\Http\Controllers;

use App\Models\MerchandiseWithdrawal;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;

/** Genera el PDF de estado de cuenta de un retiro de mercadería. */
class MerchandiseWithdrawalController extends Controller
{
    /**
     * Muestra el PDF del retiro en el navegador.
     */
    public function show(MerchandiseWithdrawal $merchandiseWithdrawal)
    {
        $merchandiseWithdrawal->load([
            'employee.activeContract.position.department',
            'employee.branch.company',
            'approvedBy',
            'items',
            'installments',
        ]);

        $settings = app(GeneralSettings::class);

        $company = $merchandiseWithdrawal->employee->company;
        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.merchandise-withdrawal', [
            'withdrawal' => $merchandiseWithdrawal,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? $settings->company_name,
            'companyRuc' => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone' => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail' => $company?->email ?? $settings->company_email ?? '',
            'employerNumber' => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city' => $company?->city ?? $settings->company_city ?? '',
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("retiro_mercaderia_{$merchandiseWithdrawal->id}_{$merchandiseWithdrawal->employee->ci}.pdf");
    }
}
