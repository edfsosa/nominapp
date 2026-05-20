<?php

namespace App\Http\Controllers;

use App\Models\MerchandiseWithdrawal;
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

        $company = $merchandiseWithdrawal->employee->company;
        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.merchandise-withdrawal', [
            'withdrawal' => $merchandiseWithdrawal,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("retiro_mercaderia_{$merchandiseWithdrawal->id}_{$merchandiseWithdrawal->employee->ci}.pdf");
    }
}
