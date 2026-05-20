<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Genera el PDF del reporte de vencimiento de contratos y períodos de prueba con agrupación adaptativa. */
class ContractExpirationReportController extends Controller
{
    /**
     * Genera y retorna el PDF del reporte.
     *
     * El modo de agrupación es adaptativo según los filtros activos:
     *   - Sin empresa ni sucursal → agrupa por empresa, luego por sucursal (company_branch)
     *   - Con empresa, sin sucursal → agrupa por sucursal (branch)
     *   - Con sucursal (o ambos) → lista plana (flat)
     *
     * Parámetros de query:
     *   tab       — 'contratos' (default) | 'prueba'
     *   companyId — filtro por empresa
     *   branchId  — filtro por sucursal
     *   days      — umbral de días al vencimiento (30, 60, 90)
     */
    public function pdf(Request $request): Response
    {
        $tab = $request->query('tab', 'contratos');
        $companyId = $request->query('companyId') ? (int) $request->query('companyId') : null;
        $branchId = $request->query('branchId') ? (int) $request->query('branchId') : null;
        $days = $request->query('days') ? (int) $request->query('days') : null;

        $query = Contract::query()
            ->select([
                'contracts.id',
                'contracts.start_date',
                'contracts.end_date',
                'contracts.trial_days',
                'contracts.type',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
                'positions.name as position_name',
                DB::raw('DATEDIFF(contracts.end_date, CURDATE()) as days_until_expiry'),
                DB::raw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) as days_until_trial_end'),
                DB::raw('DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY) as trial_end_date'),
            ])
            ->join('employees', 'employees.id', '=', 'contracts.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('positions', 'positions.id', '=', 'contracts.position_id')
            ->where('contracts.status', 'active')
            ->when($companyId, fn ($q) => $q->where('branches.company_id', $companyId))
            ->when($branchId, fn ($q) => $q->where('employees.branch_id', $branchId));

        if ($tab === 'prueba') {
            $query
                ->where('contracts.trial_days', '>', 0)
                ->whereRaw('DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY) >= CURDATE()')
                ->orderBy('companies.name')
                ->orderBy('branches.name')
                ->orderByRaw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) ASC');

            if ($days) {
                $query
                    ->whereRaw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) >= 0')
                    ->whereRaw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) <= ?', [$days]);
            }
        } else {
            $query
                ->whereNotNull('contracts.end_date')
                ->orderBy('companies.name')
                ->orderBy('branches.name')
                ->orderByRaw('DATEDIFF(contracts.end_date, CURDATE()) ASC');

            if ($days) {
                $query
                    ->whereRaw('DATEDIFF(contracts.end_date, CURDATE()) >= 0')
                    ->whereRaw('DATEDIFF(contracts.end_date, CURDATE()) <= ?', [$days]);
            }
        }

        $contracts = $query->get();

        // Modo de agrupación adaptativo
        $groupMode = match (true) {
            $companyId === null && $branchId === null => 'company_branch',
            $companyId !== null && $branchId === null => 'branch',
            default => 'flat',
        };

        $groups = match ($groupMode) {
            'company_branch' => $contracts
                ->groupBy('company_name')
                ->map(fn (Collection $g) => $g->groupBy('branch_name')),
            'branch' => $contracts->groupBy('branch_name'),
            default => null,
        };

        // Resolver empresa para encabezado
        $uniqueCompanyIds = $contracts->pluck('company_id')->filter()->unique();
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

        $totalContracts = $contracts->count();
        $daysField = $tab === 'prueba' ? 'days_until_trial_end' : 'days_until_expiry';
        $avgDays = $totalContracts > 0 ? round($contracts->avg($daysField)) : 0;

        $pdf = Pdf::loadView('pdf.contract-expiration-report', compact(
            'contracts', 'groups', 'groupMode', 'tab', 'days',
            'showCompanyHeader',
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
            'companyPhone', 'companyEmail', 'employerNumber', 'city',
            'totalContracts', 'avgDays', 'daysField'
        ))->setPaper('a4', 'portrait');

        $suffix = $days ? "-{$days}d" : '';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="reporte-contratos-'.$tab.$suffix.'.pdf"',
        ]);
    }
}
