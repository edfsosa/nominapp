<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeReportExport;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Genera el reporte de empleados en PDF con filtros y columnas seleccionables. */
class EmployeeReportController extends Controller
{
    /**
     * Genera y retorna el PDF del reporte de empleados.
     *
     * Agrupación adaptativa según filtros:
     *   - Con filtro de empresa o una sola empresa → lista plana con encabezado de empresa
     *   - Sin filtro con múltiples empresas → agrupa por empresa
     */
    public function pdf(Request $request): Response
    {
        $companyId = $request->query('companyId') ? (int) $request->query('companyId') : null;
        $branchId = $request->query('branchId') ? (int) $request->query('branchId') : null;
        $gender = $request->query('gender') ?: null;
        $birthMonth = $request->query('birthMonth') ? (int) $request->query('birthMonth') : null;
        $status = $request->query('status') ?: null;
        $departmentId = $request->query('departmentId') ? (int) $request->query('departmentId') : null;
        $contractType = $request->query('contractType') ?: null;
        $paymentMethod = $request->query('paymentMethod') ?: null;

        $columnsParam = $request->query('columns');
        $selectedColumns = $columnsParam
            ? explode(',', $columnsParam)
            : array_keys(EmployeeReportExport::availableColumns());

        $employees = Employee::query()
            ->select([
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'employees.gender',
                'employees.birth_date',
                'employees.status',
                'employees.phone',
                'employees.email',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
                'contracts.start_date as hire_date',
                'contracts.salary',
                'contracts.salary_type',
                'contracts.type as contract_type',
                'contracts.payment_method',
                'positions.name as position_name',
                'departments.name as department_name',
                DB::raw('TIMESTAMPDIFF(YEAR, contracts.start_date, CURDATE()) AS years_of_service'),
            ])
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->join('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('contracts', function ($join) {
                $join->on('contracts.employee_id', '=', 'employees.id')
                    ->where('contracts.status', '=', 'active');
            })
            ->leftJoin('positions', 'positions.id', '=', 'contracts.position_id')
            ->leftJoin('departments', 'departments.id', '=', 'contracts.department_id')
            ->when($companyId, fn ($q) => $q->where('branches.company_id', $companyId))
            ->when($branchId, fn ($q) => $q->where('employees.branch_id', $branchId))
            ->when($gender, fn ($q) => $q->where('employees.gender', $gender))
            ->when($birthMonth, fn ($q) => $q->whereRaw('MONTH(employees.birth_date) = ?', [$birthMonth]))
            ->when($status, fn ($q) => $q->where('employees.status', $status))
            ->when($departmentId, fn ($q) => $q->where('contracts.department_id', $departmentId))
            ->when($contractType, fn ($q) => $q->where('contracts.type', $contractType))
            ->when($paymentMethod, fn ($q) => $q->where('contracts.payment_method', $paymentMethod))
            ->orderBy('companies.name')
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->get();

        $totalCount = $employees->count();
        $byGender = $employees->groupBy('gender')->map->count();
        $byStatus = $employees->groupBy('status')->map->count();
        $avgYears = $employees->whereNotNull('years_of_service')->avg('years_of_service');

        $uniqueCompanyIds = $employees->pluck('company_id')->filter()->unique();
        $resolvedCompanyId = $companyId ?? ($uniqueCompanyIds->count() === 1 ? $uniqueCompanyIds->first() : null);
        $groupMode = ($companyId !== null || $uniqueCompanyIds->count() <= 1) ? 'flat' : 'company';
        $groups = $groupMode === 'company' ? $employees->groupBy('company_name') : null;

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

        $monthOptions = Employee::getMonthOptions();
        $genderOptions = Employee::getGenderOptions();
        $statusOptions = Employee::getStatusOptions();
        $contractTypes = Contract::getTypeOptions();
        $paymentMethods = Employee::getPaymentMethodOptions();
        $columnLabels = EmployeeReportExport::availableColumns();

        $pdf = Pdf::loadView('pdf.employee-report', compact(
            'employees', 'groups', 'groupMode',
            'gender', 'birthMonth', 'status', 'contractType', 'paymentMethod',
            'totalCount', 'byGender', 'byStatus', 'avgYears',
            'showCompanyHeader',
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
            'companyPhone', 'companyEmail', 'city',
            'monthOptions', 'genderOptions', 'statusOptions', 'contractTypes', 'paymentMethods',
            'selectedColumns', 'columnLabels'
        ))->setPaper('a4', 'landscape');

        $filename = 'reporte-empleados-'.now()->format('Ymd_His').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
