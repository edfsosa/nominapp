<?php

namespace App\Http\Controllers;

use App\Models\Advance;
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

        $company = $advance->employee->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.advance', [
            'advance' => $advance,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
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

        $company = $advances->first()->employee->company;

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;

        $pdf = Pdf::loadView('pdf.advances-bulk', [
            'advances' => $advances,
            'companyLogo' => $companyLogo && file_exists($companyLogo) ? $companyLogo : null,
            'companyName' => $company?->name ?? '',
            'companyRuc' => $company?->ruc ?? '',
            'companyAddress' => $company?->address ?? '',
            'companyPhone' => $company?->phone ?? '',
            'companyEmail' => $company?->email ?? '',
            'employerNumber' => $company?->employer_number ?? '',
            'city' => $company?->city ?? '',
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('adelantos_'.now()->format('Y_m_d_H_i_s').'.pdf');
    }
}
