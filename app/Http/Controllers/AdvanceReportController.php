<?php

namespace App\Http\Controllers;

use App\Models\Advance;
use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Genera el informe de adelantos de salario en PDF con los filtros activos del reporte. */
class AdvanceReportController extends Controller
{
    /**
     * Genera y retorna el PDF del informe de adelantos.
     *
     * Agrupación adaptativa según filtros:
     *   - Con filtro de empresa → lista plana con encabezado de esa empresa
     *   - Sin filtro, una sola empresa en resultados → lista plana con ese encabezado
     *   - Sin filtro, múltiples empresas → agrupa por empresa con sección por empresa
     */
    public function pdf(Request $request): Response
    {
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: now()->endOfMonth()->toDateString();
        $companyId = $request->query('companyId') ? (int) $request->query('companyId') : null;
        $branchId = $request->query('branchId') ? (int) $request->query('branchId') : null;
        $status = $request->query('status') ?: null;
        $employeeId = $request->query('employeeId') ? (int) $request->query('employeeId') : null;
        $paymentMethod = $request->query('paymentMethod') ?: null;

        $advances = Advance::query()
            ->select([
                'advances.id',
                'advances.amount',
                'advances.status',
                'advances.payment_method',
                'advances.notes',
                'advances.created_at',
                'advances.approved_at',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
                'users.name as approved_by_name',
            ])
            ->join('employees', 'employees.id', '=', 'advances.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('users', 'users.id', '=', 'advances.approved_by_id')
            ->whereDate('advances.created_at', '>=', $from)
            ->whereDate('advances.created_at', '<=', $to)
            ->when($companyId, fn ($q) => $q->where('branches.company_id', $companyId))
            ->when($branchId, fn ($q) => $q->where('employees.branch_id', $branchId))
            ->when($status, fn ($q) => $q->where('advances.status', $status))
            ->when($employeeId, fn ($q) => $q->where('advances.employee_id', $employeeId))
            ->when($paymentMethod, fn ($q) => $q->where('advances.payment_method', $paymentMethod))
            ->orderBy('companies.name')
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('advances.created_at')
            ->get();

        $totalAmount = $advances->sum('amount');
        $totalEmployees = $advances->unique('ci')->count();
        $countByStatus = $advances->groupBy('status')->map->count();
        $amountTransfer = $advances->where('payment_method', 'transfer')->sum('amount');
        $amountCash = $advances->where('payment_method', 'cash')->sum('amount');

        // Agrupación adaptativa
        $uniqueCompanyIds = $advances->pluck('company_id')->filter()->unique();
        $resolvedCompanyId = $companyId ?? ($uniqueCompanyIds->count() === 1 ? $uniqueCompanyIds->first() : null);

        $groupMode = ($companyId !== null || $uniqueCompanyIds->count() <= 1) ? 'flat' : 'company';
        $groups = $groupMode === 'company' ? $advances->groupBy('company_name') : null;

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

        $pdf = Pdf::loadView('pdf.advance-report', compact(
            'advances', 'groups', 'groupMode',
            'from', 'to', 'fromFormatted', 'toFormatted',
            'status', 'paymentMethod',
            'totalAmount', 'totalEmployees', 'countByStatus',
            'amountTransfer', 'amountCash',
            'showCompanyHeader',
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
            'companyPhone', 'companyEmail', 'city',
        ))->setPaper('a4', 'portrait');

        $filename = 'reporte-adelantos-'.str_replace('-', '', $from).'_'.str_replace('-', '', $to).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
