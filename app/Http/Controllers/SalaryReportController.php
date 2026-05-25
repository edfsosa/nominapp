<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Payroll;
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

        $pdf = Pdf::loadView('pdf.salary-report', compact(
            'payrolls', 'period',
            'totalBaseSalary', 'totalPerceptions',
            'totalIps', 'totalLoans', 'totalJudicial', 'totalVoluntary',
            'totalDeductions', 'totalNet', 'totalEmployees',
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
        ))->setPaper('a4', 'landscape');

        $periodSlug = $period ? str_replace(' ', '_', $period->name) : 'reporte';
        $filename = 'salarios_'.$periodSlug.'_'.now()->format('Y_m_d').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
