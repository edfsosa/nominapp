<?php

namespace App\Http\Controllers;

use App\Exports\ContractReportExport;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * Genera el PDF del reporte de contratos con agrupación adaptativa.
 *
 * Soporta los 7 tabs del ContractReport:
 *   vencer, prueba, sin_contrato, antiguedad, suspendidos, activos, rescindidos.
 *
 * El modo de agrupación es adaptativo según los filtros activos:
 *   - Sin empresa ni sucursal → agrupa por empresa → por sucursal (company_branch)
 *   - Con empresa, sin sucursal → agrupa por sucursal (branch)
 *   - Con sucursal (o ambos) → lista plana (flat)
 */
class ContractReportController extends Controller
{
    /**
     * Genera y retorna el PDF del reporte de contratos.
     *
     * Parámetros de query:
     *   tab            — tab activo (default: 'vencer')
     *   companyId      — filtro por empresa
     *   branchId       — filtro por sucursal
     *   days        — umbral de días al vencimiento (solo vencer/prueba)
     *   period      — meses hacia atrás (solo rescindidos)
     *   columns     — columnas seleccionadas separadas por coma
     *   orientation — 'portrait' | 'landscape'
     */
    public function pdf(Request $request): Response
    {
        $tab = $request->query('tab', 'vencer');
        $companyId = $request->query('companyId') ? (int) $request->query('companyId') : null;
        $branchId = $request->query('branchId') ? (int) $request->query('branchId') : null;
        $days = $request->query('days') ? (int) $request->query('days') : null;
        $period = $request->query('period') ? (int) $request->query('period') : null;

        $columnsParam = $request->query('columns');
        $selectedColumns = $columnsParam
            ? explode(',', $columnsParam)
            : ContractReportExport::defaultColumns($tab);

        $orientation = in_array($request->query('orientation'), ['portrait', 'landscape'])
            ? $request->query('orientation')
            : 'landscape';

        $columnLabels = ContractReportExport::availableColumns($tab);

        // ── Query según tab ──────────────────────────────────────────────────
        $export = new ContractReportExport($tab, $companyId, $branchId, $days, $period, $selectedColumns);
        $records = $export->query()->get();

        // ── Modo de agrupación adaptativo ────────────────────────────────────
        $groupMode = match (true) {
            $companyId === null && $branchId === null => 'company_branch',
            $companyId !== null && $branchId === null => 'branch',
            default => 'flat',
        };

        $groups = match ($groupMode) {
            'company_branch' => $records
                ->groupBy('company_name')
                ->map(fn (Collection $g) => $g->groupBy('branch_name')),
            'branch' => $records->groupBy('branch_name'),
            default => null,
        };

        // ── Empresa para encabezado ──────────────────────────────────────────
        $uniqueCompanyIds = $records->pluck('company_id')->filter()->unique();
        $resolvedCompanyId = $companyId ?? ($uniqueCompanyIds->count() === 1 ? $uniqueCompanyIds->first() : null);
        $company = $resolvedCompanyId ? Company::find($resolvedCompanyId) : null;
        if ($company === null && Company::active()->count() === 1) {
            $company = Company::first();
        }

        $showCompanyHeader = $company !== null;
        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;
        $companyLogo = $companyLogo && file_exists($companyLogo) ? $companyLogo : null;

        $companyName = $company?->name ?? '';
        $companyRuc = $company?->ruc ?? '';
        $companyAddress = $company?->address ?? '';
        $companyPhone = $company?->phone ?? '';
        $companyEmail = $company?->email ?? '';
        $employerNumber = $company?->employer_number ?? '';
        $city = $company?->city ?? '';

        // ── Estadísticas para el total general ──────────────────────────────
        $totalRecords = $records->count();

        // Para tabs con días restantes, calcular promedio
        $avgDays = null;
        if ($tab === 'vencer' && $totalRecords > 0) {
            $avgDays = (int) round($records->avg('days_until_expiry'));
        } elseif ($tab === 'prueba' && $totalRecords > 0) {
            $avgDays = (int) round($records->avg('days_until_trial_end'));
        }

        $pdf = Pdf::loadView('pdf.contract-report', compact(
            'records', 'groups', 'groupMode', 'tab', 'days', 'period',
            'showCompanyHeader',
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
            'companyPhone', 'companyEmail', 'employerNumber', 'city',
            'totalRecords', 'avgDays',
            'selectedColumns', 'columnLabels', 'orientation'
        ))->setPaper('a4', $orientation);

        $suffix = $days ? "-{$days}d" : ($period ? "-{$period}m" : '');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte-contratos-'.$tab.$suffix.'.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
