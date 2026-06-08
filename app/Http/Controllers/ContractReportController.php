<?php

namespace App\Http\Controllers;

use App\Exports\ContractReportExport;
use App\Models\Company;
use App\Models\Department;
use App\Models\Position;
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
     *   type           — filtro por tipo de contrato
     *   salaryType     — filtro por tipo de salario
     *   departmentId   — filtro por departamento
     *   positionId     — filtro por cargo
     *   days           — umbral de días al vencimiento (solo vencer/prueba)
     *   period         — meses hacia atrás (solo rescindidos)
     *   startDateFrom  — inicio de contrato desde (Y-m-d)
     *   startDateUntil — inicio de contrato hasta (Y-m-d)
     *   endDateFrom    — vencimiento desde (Y-m-d, solo vencer)
     *   endDateUntil   — vencimiento hasta (Y-m-d, solo vencer)
     *   terminatedFrom — rescisión desde (Y-m-d, solo rescindidos)
     *   terminatedUntil— rescisión hasta (Y-m-d, solo rescindidos)
     *   columns        — columnas seleccionadas separadas por coma
     *   orientation    — 'portrait' | 'landscape'
     */
    public function pdf(Request $request): Response
    {
        $tab = $request->query('tab', 'vencer');

        $filters = [
            'companyId' => $request->query('companyId') ? (int) $request->query('companyId') : null,
            'branchId' => $request->query('branchId') ? (int) $request->query('branchId') : null,
            'type' => $request->query('type') ?: null,
            'salaryType' => $request->query('salaryType') ?: null,
            'departmentId' => $request->query('departmentId') ? (int) $request->query('departmentId') : null,
            'positionId' => $request->query('positionId') ? (int) $request->query('positionId') : null,
            'days' => $request->query('days') ? (int) $request->query('days') : null,
            'period' => $request->query('period') ? (int) $request->query('period') : null,
            'startDateFrom' => $request->query('startDateFrom') ?: null,
            'startDateUntil' => $request->query('startDateUntil') ?: null,
            'endDateFrom' => $request->query('endDateFrom') ?: null,
            'endDateUntil' => $request->query('endDateUntil') ?: null,
            'terminatedFrom' => $request->query('terminatedFrom') ?: null,
            'terminatedUntil' => $request->query('terminatedUntil') ?: null,
        ];

        $columnsParam = $request->query('columns');
        $selectedColumns = $columnsParam
            ? explode(',', $columnsParam)
            : ContractReportExport::defaultColumns($tab);

        $orientation = in_array($request->query('orientation'), ['portrait', 'landscape'])
            ? $request->query('orientation')
            : 'landscape';

        $columnLabels = ContractReportExport::availableColumns($tab);

        // ── Query según tab ──────────────────────────────────────────────────
        $export = new ContractReportExport($tab, $selectedColumns, $filters);
        $records = $export->query()->get();

        // ── Modo de agrupación adaptativo ────────────────────────────────────
        $companyId = $filters['companyId'];
        $branchId = $filters['branchId'];

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

        // ── Etiquetas de filtros activos para el subtítulo del PDF ───────────
        $activeFilterLabels = [];
        if ($filters['type']) {
            $activeFilterLabels[] = 'Tipo: '.(\App\Models\Contract::getTypeLabel($filters['type']));
        }
        if ($filters['salaryType']) {
            $activeFilterLabels[] = 'Salario: '.(\App\Models\Contract::getSalaryTypeLabel($filters['salaryType']));
        }
        if ($filters['departmentId']) {
            $dept = Department::find($filters['departmentId']);
            if ($dept) {
                $activeFilterLabels[] = 'Dpto: '.$dept->name;
            }
        }
        if ($filters['positionId']) {
            $pos = Position::find($filters['positionId']);
            if ($pos) {
                $activeFilterLabels[] = 'Cargo: '.$pos->name;
            }
        }

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
            'records', 'groups', 'groupMode', 'tab', 'filters', 'activeFilterLabels',
            'showCompanyHeader',
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
            'companyPhone', 'companyEmail', 'employerNumber', 'city',
            'totalRecords', 'avgDays',
            'selectedColumns', 'columnLabels', 'orientation'
        ))->setPaper('a4', $orientation);

        $suffix = $filters['days'] ? "-{$filters['days']}d" : ($filters['period'] ? "-{$filters['period']}m" : '');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte-contratos-'.$tab.$suffix.'.pdf"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
