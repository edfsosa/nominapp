<?php

namespace App\Http\Controllers;

use App\Exports\MerchandiseWithdrawalsSheet;
use App\Models\Company;
use App\Models\MerchandiseWithdrawal;
use App\Models\MerchandiseWithdrawalInstallment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Genera el reporte de retiros de mercadería en PDF con los filtros activos. */
class MerchandiseReportController extends Controller
{
    /**
     * Genera y retorna el PDF del reporte de retiros de mercadería.
     *
     * Agrupación adaptativa según filtros:
     *   - Con filtro de empresa o una sola empresa en resultados → lista plana
     *   - Sin filtro con múltiples empresas → agrupa por empresa
     *
     * Cada retiro incluye su tabla de cuotas anidada.
     */
    public function pdf(Request $request): Response
    {
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: now()->endOfMonth()->toDateString();
        $companyId = $request->query('companyId') ? (int) $request->query('companyId') : null;
        $branchId = $request->query('branchId') ? (int) $request->query('branchId') : null;
        $status = $request->query('status') ?: null;
        $employeeId = $request->query('employeeId') ? (int) $request->query('employeeId') : null;

        $columnsParam = $request->query('columns');
        $selectedColumns = $columnsParam
            ? explode(',', $columnsParam)
            : MerchandiseWithdrawalsSheet::defaultColumns();

        $orientation = in_array($request->query('orientation'), ['portrait', 'landscape'])
            ? $request->query('orientation')
            : 'landscape';

        $columnLabels = MerchandiseWithdrawalsSheet::availableColumns();

        $withdrawals = MerchandiseWithdrawal::query()
            ->select([
                'merchandise_withdrawals.id',
                'merchandise_withdrawals.total_amount',
                'merchandise_withdrawals.installments_count',
                'merchandise_withdrawals.installment_amount',
                'merchandise_withdrawals.outstanding_balance',
                'merchandise_withdrawals.status',
                'merchandise_withdrawals.notes',
                'merchandise_withdrawals.approved_at',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
                'approvers.name as approved_by_name',
                DB::raw('(SELECT COUNT(*) FROM merchandise_withdrawal_installments
                          WHERE merchandise_withdrawal_id = merchandise_withdrawals.id
                          AND status = "paid") AS paid_installments_count'),
            ])
            ->join('employees', 'employees.id', '=', 'merchandise_withdrawals.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->join('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('users as approvers', 'approvers.id', '=', 'merchandise_withdrawals.approved_by_id')
            ->whereDate('merchandise_withdrawals.created_at', '>=', $from)
            ->whereDate('merchandise_withdrawals.created_at', '<=', $to)
            ->when($companyId, fn ($q) => $q->where('branches.company_id', $companyId))
            ->when($branchId, fn ($q) => $q->where('employees.branch_id', $branchId))
            ->when($status, fn ($q) => $q->where('merchandise_withdrawals.status', $status))
            ->when($employeeId, fn ($q) => $q->where('merchandise_withdrawals.employee_id', $employeeId))
            ->orderBy('companies.name')
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('merchandise_withdrawals.created_at')
            ->get();

        // Cargar cuotas de cada retiro agrupadas por withdrawal_id
        $withdrawalIds = $withdrawals->pluck('id')->all();
        $installmentsByWithdrawal = MerchandiseWithdrawalInstallment::query()
            ->whereIn('merchandise_withdrawal_id', $withdrawalIds)
            ->orderBy('merchandise_withdrawal_id')
            ->orderBy('installment_number')
            ->get()
            ->groupBy('merchandise_withdrawal_id');

        // Estadísticas globales
        $totalAmount = $withdrawals->sum('total_amount');
        $totalPending = $withdrawals->sum('outstanding_balance');
        $totalEmployees = $withdrawals->unique('ci')->count();
        $countByStatus = $withdrawals->groupBy('status')->map->count();

        // Agrupación adaptativa
        $uniqueCompanyIds = $withdrawals->pluck('company_id')->filter()->unique();
        $resolvedCompanyId = $companyId ?? ($uniqueCompanyIds->count() === 1 ? $uniqueCompanyIds->first() : null);
        $groupMode = ($companyId !== null || $uniqueCompanyIds->count() <= 1) ? 'flat' : 'company';
        $groups = $groupMode === 'company' ? $withdrawals->groupBy('company_name') : null;

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
        $city = $company?->city ?? '';

        $fromFormatted = date('d/m/Y', strtotime($from));
        $toFormatted = date('d/m/Y', strtotime($to));

        $pdf = Pdf::loadView('pdf.merchandise-report', compact(
            'withdrawals', 'groups', 'groupMode',
            'installmentsByWithdrawal',
            'from', 'to', 'fromFormatted', 'toFormatted',
            'status',
            'totalAmount', 'totalPending', 'totalEmployees', 'countByStatus',
            'showCompanyHeader',
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
            'companyPhone', 'companyEmail', 'city',
            'selectedColumns', 'columnLabels', 'orientation'
        ))->setPaper('a4', $orientation);

        $filename = 'reporte-mercaderias-'.str_replace('-', '', $from).'_'.str_replace('-', '', $to).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
