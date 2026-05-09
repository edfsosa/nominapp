<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Vacation;
use App\Settings\GeneralSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/** Genera el informe de vacaciones en PDF con agrupación adaptativa según filtros activos. */
class VacationReportController extends Controller
{
    /** @var array<int, string> */
    public const MONTHS = [
        1 => 'Enero',    2 => 'Febrero',   3 => 'Marzo',
        4 => 'Abril',    5 => 'Mayo',       6 => 'Junio',
        7 => 'Julio',    8 => 'Agosto',     9 => 'Septiembre',
        10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * Genera y retorna el PDF del informe de vacaciones.
     *
     * El modo de agrupación es adaptativo según los filtros activos:
     *   - Sin mes ni empresa → agrupa por empresa, luego por mes
     *   - Sin mes, con empresa → agrupa por mes
     *   - Con mes, sin empresa → agrupa por empresa
     *   - Con mes y empresa → lista plana
     *
     * @param  Request  $request
     * @return Response
     */
    public function pdf(Request $request): Response
    {
        $year      = (int) ($request->query('year', now()->year));
        $month     = $request->query('month')     ? (int) $request->query('month')     : null;
        $companyId = $request->query('companyId') ? (int) $request->query('companyId') : null;
        $branchId  = $request->query('branchId')  ? (int) $request->query('branchId')  : null;
        $status    = $request->query('status') ?: 'approved';

        $vacations = Vacation::query()
            ->select([
                'vacations.id',
                'vacations.start_date',
                'vacations.end_date',
                'vacations.return_date',
                'vacations.business_days',
                'vacations.type',
                'vacations.status',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
            ])
            ->join('employees', 'employees.id', '=', 'vacations.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'branches.company_id')
            ->where('vacations.status', $status)
            ->whereYear('vacations.start_date', $year)
            ->when($month, fn ($q) => $q->whereMonth('vacations.start_date', $month))
            ->when($companyId, fn ($q) => $q->where('branches.company_id', $companyId))
            ->when($branchId, fn ($q) => $q->where('employees.branch_id', $branchId))
            ->orderBy('companies.name')
            ->orderByRaw('MONTH(vacations.start_date)')
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('vacations.start_date')
            ->get();

        // Modo de agrupación adaptativo
        $groupMode = match (true) {
            $month === null && $companyId === null => 'company_month',
            $month === null                        => 'month',
            $companyId === null                    => 'company',
            default                                => 'flat',
        };

        $groups = match ($groupMode) {
            'company_month' => $vacations
                ->groupBy('company_name')
                ->map(fn (Collection $g) => $g->groupBy(
                    fn ($v) => (int) date('n', strtotime($v->start_date))
                )),
            'company' => $vacations->groupBy('company_name'),
            'month'   => $vacations->groupBy(
                fn ($v) => (int) date('n', strtotime($v->start_date))
            ),
            default => null,
        };

        // Resolver empresa para el encabezado: filtro explícito o única empresa en resultados
        $uniqueCompanyIds = $vacations->pluck('company_id')->filter()->unique();
        $resolvedCompanyId = $companyId ?? ($uniqueCompanyIds->count() === 1 ? $uniqueCompanyIds->first() : null);

        $company  = $resolvedCompanyId ? Company::find($resolvedCompanyId) : null;
        $settings = app(GeneralSettings::class);
        $showCompanyHeader = $company !== null;

        $logoPath    = $company?->logo ?? $settings->company_logo;
        $companyLogo = $logoPath ? storage_path('app/public/' . $logoPath) : null;
        $companyLogo = $companyLogo && file_exists($companyLogo) ? $companyLogo : null;

        $companyName    = $company?->name            ?? $settings->company_name;
        $companyRuc     = $company?->ruc             ?? $settings->company_ruc            ?? '';
        $companyAddress = $company?->address         ?? $settings->company_address        ?? '';
        $companyPhone   = $company?->phone           ?? $settings->company_phone          ?? '';
        $companyEmail   = $company?->email           ?? $settings->company_email          ?? '';
        $employerNumber = $company?->employer_number ?? $settings->company_employer_number ?? '';
        $city           = $company?->city            ?? $settings->company_city           ?? '';

        $monthName         = $month ? (self::MONTHS[$month] ?? '') : null;
        $totalBusinessDays = $vacations->sum('business_days');
        $totalEmployees    = $vacations->unique('ci')->count();
        $months            = self::MONTHS;

        $pdf = Pdf::loadView('pdf.vacation-report', compact(
            'vacations', 'groups', 'groupMode', 'months',
            'year', 'month', 'monthName', 'status',
            'showCompanyHeader',
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
            'companyPhone', 'companyEmail', 'employerNumber', 'city',
            'totalBusinessDays', 'totalEmployees'
        ))->setPaper('a4', 'portrait');

        $monthSuffix = $month ? '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) : '';

        return response($pdf->output(), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="informe-vacaciones-' . $year . $monthSuffix . '.pdf"',
        ]);
    }
}
