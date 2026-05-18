<?php

namespace App\Http\Controllers;

use App\Models\Advance;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

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

    /**
     * Genera un PDF masivo con múltiples adelantos (2 por página).
     *
     * Los IDs se reciben como query string separados por coma (?ids=1,2,3).
     */
    public function bulkPdf(Request $request)
    {
        $ids = array_filter(explode(',', $request->query('ids', '')));

        if (empty($ids)) {
            abort(400, 'No se especificaron adelantos.');
        }

        $advances = Advance::whereIn('id', $ids)
            ->with(['employee.activeContract.position.department', 'employee.branch.company'])
            ->orderBy('id')
            ->get();

        if ($advances->isEmpty()) {
            abort(404, 'No se encontraron los adelantos solicitados.');
        }

        $settings = app(GeneralSettings::class);

        $firstEmployee = $advances->first()->employee;
        $company = $firstEmployee->company;

        $logoPath = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.advances-bulk', [
            'advances' => $advances,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? $settings->company_name,
            'companyRuc' => $company?->ruc ?? $settings->company_ruc ?? '',
            'companyAddress' => $company?->address ?? $settings->company_address ?? '',
            'companyPhone' => $company?->phone ?? $settings->company_phone ?? '',
            'companyEmail' => $company?->email ?? $settings->company_email ?? '',
            'employerNumber' => $company?->employer_number ?? $settings->company_employer_number ?? '',
            'city' => $company?->city ?? $settings->company_city ?? '',
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('adelantos_'.now()->format('Y_m_d_H_i_s').'.pdf');
    }
}
