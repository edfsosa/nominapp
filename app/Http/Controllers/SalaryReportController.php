<?php

namespace App\Http\Controllers;

use App\Exports\SalaryReportExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Genera el reporte de salarios en PDF con los filtros activos de la página de reporte. */
class SalaryReportController extends Controller
{
    /**
     * Genera y retorna el PDF del reporte de salarios.
     *
     * El PDF muestra una tabla con todos los empleados de la planilla seleccionada,
     * con desglose de percepciones, deducciones por tipo y neto a pagar.
     */
    public function pdf(Request $request): Response
    {
        $periodId = $request->query('periodId') ? (int) $request->query('periodId') : null;
        $companyId = $request->query('companyId') ? (int) $request->query('companyId') : null;
        $branchId = $request->query('branchId') ? (int) $request->query('branchId') : null;
        $status = $request->query('status') ?: null;
        $paymentMethod = $request->query('paymentMethod') ?: null;

        $columnsParam = $request->query('columns');
        $selectedColumns = $columnsParam
            ? explode(',', $columnsParam)
            : SalaryReportExport::defaultColumns();

        $subtablesParam = $request->query('subtables', 'perceptions,deductions,payment_methods');
        $showSubtables = $subtablesParam !== '' ? explode(',', $subtablesParam) : [];

        $orientation = in_array($request->query('orientation'), ['portrait', 'landscape'])
            ? $request->query('orientation')
            : 'landscape';

        $columnLabels = SalaryReportExport::availableColumns();

        $period = $periodId ? PayrollPeriod::find($periodId) : null;

        $payrolls = Payroll::query()
            ->select([
                'payrolls.id',
                'payrolls.base_salary',
                'payrolls.total_perceptions',
                'payrolls.total_deductions',
                'payrolls.net_salary',
                'payrolls.status',
                'payrolls.payment_method',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
                \DB::raw('(SELECT positions.name FROM contracts INNER JOIN positions ON positions.id = contracts.position_id WHERE contracts.employee_id = employees.id AND contracts.status = \'active\' LIMIT 1) as position_name'),
                \DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'legal\') as ips_amount'),
                \DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'loan\') as loan_amount'),
                \DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'judicial\') as judicial_amount'),
                \DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'voluntary\') as voluntary_amount'),
            ])
            ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->join('companies', 'companies.id', '=', 'branches.company_id')
            ->when($periodId, fn ($q) => $q->where('payrolls.payroll_period_id', $periodId))
            ->when($companyId, fn ($q) => $q->where('branches.company_id', $companyId))
            ->when($branchId, fn ($q) => $q->where('employees.branch_id', $branchId))
            ->when($status, fn ($q) => $q->where('payrolls.status', $status))
            ->when($paymentMethod, fn ($q) => $q->where('payrolls.payment_method', $paymentMethod))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->get();

        // Desglose agrupado por concepto (percepciones y deducciones)
        $payrollIds = $payrolls->pluck('id');

        $perceptionSummary = PayrollItem::whereIn('payroll_id', $payrollIds)
            ->where('type', 'perception')
            ->selectRaw('description, COUNT(DISTINCT payroll_id) as employees_count, SUM(amount) as total_amount')
            ->groupBy('description')
            ->orderByDesc('total_amount')
            ->get();

        $deductionSummary = PayrollItem::whereIn('payroll_id', $payrollIds)
            ->where('type', 'deduction')
            ->selectRaw('description, COUNT(DISTINCT payroll_id) as employees_count, SUM(amount) as total_amount')
            ->groupBy('description')
            ->orderByDesc('total_amount')
            ->get();

        // Totales generales
        $totalBaseSalary = $payrolls->sum('base_salary');
        $totalPerceptions = $payrolls->sum('total_perceptions');
        $totalIps = $payrolls->sum('ips_amount');
        $totalLoans = $payrolls->sum('loan_amount');
        $totalJudicial = $payrolls->sum('judicial_amount');
        $totalVoluntary = $payrolls->sum('voluntary_amount');
        $totalDeductions = $payrolls->sum('total_deductions');
        $totalNet = $payrolls->sum('net_salary');
        $totalEmployees = $payrolls->count();

        // Empresa para encabezado
        $uniqueCompanyIds = $payrolls->pluck('company_id')->filter()->unique();
        $resolvedCompanyId = $companyId ?? ($uniqueCompanyIds->count() === 1 ? $uniqueCompanyIds->first() : null);
        $company = $resolvedCompanyId ? Company::find($resolvedCompanyId) : null;
        if ($company === null && Company::active()->count() === 1) {
            $company = Company::first();
        }

        $logoPath = $company?->logo;
        $companyLogo = $logoPath ? storage_path('app/public/'.$logoPath) : null;
        $companyLogo = ($companyLogo && file_exists($companyLogo)) ? $companyLogo : null;

        $companyName = $company?->name ?? '';
        $companyRuc = $company?->ruc ?? '';
        $companyAddress = $company?->address ?? '';

        // Filtros aplicados para mostrar en el PDF
        $appliedFilters = [];
        if ($period) {
            $appliedFilters['Planilla'] = $period->name.' ('.$period->start_date->format('d/m/Y').' — '.$period->end_date->format('d/m/Y').')';
        }
        if ($companyId && $company) {
            $appliedFilters['Empresa'] = $company->name;
        }
        if ($branchId) {
            $appliedFilters['Sucursal'] = Branch::find($branchId)?->name ?? 'ID '.$branchId;
        }
        if ($status) {
            $appliedFilters['Estado'] = Payroll::getStatusLabels()[$status] ?? $status;
        }
        if ($paymentMethod) {
            $appliedFilters['Método de pago'] = Payroll::getPaymentMethodLabels()[$paymentMethod] ?? $paymentMethod;
        }

        // Resumen por método de pago
        $paymentMethodSummary = $payrolls
            ->groupBy('payment_method')
            ->map(fn ($group, $method) => [
                'label' => Payroll::getPaymentMethodLabels()[$method] ?? $method,
                'count' => $group->count(),
                'total_net' => $group->sum('net_salary'),
            ])
            ->values()
            ->sortByDesc('total_net')
            ->values();

        $pdf = Pdf::loadView('pdf.salary-report', compact(
            'payrolls', 'period',
            'totalBaseSalary', 'totalPerceptions',
            'totalIps', 'totalLoans', 'totalJudicial', 'totalVoluntary',
            'totalDeductions', 'totalNet', 'totalEmployees',
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
            'perceptionSummary', 'deductionSummary',
            'appliedFilters', 'paymentMethodSummary',
            'selectedColumns', 'columnLabels', 'showSubtables', 'orientation'
        ))->setPaper('a4', $orientation);

        $periodSlug = $period ? str_replace(' ', '_', $period->name) : 'reporte';
        $filename = 'salarios_'.$periodSlug.'_'.now()->format('Y_m_d').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
