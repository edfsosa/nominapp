<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/** Genera el reporte de empleados en PDF con los filtros activos. */
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
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
                'contracts.start_date as hire_date',
                'contracts.salary',
                'contracts.salary_type',
                'positions.name as position_name',
                DB::raw('TIMESTAMPDIFF(YEAR, contracts.start_date, CURDATE()) AS years_of_service'),
            ])
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->join('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('contracts', function ($join) {
                $join->on('contracts.employee_id', '=', 'employees.id')
                    ->where('contracts.status', '=', 'active');
            })
            ->leftJoin('positions', 'positions.id', '=', 'contracts.position_id')
            ->when($companyId, fn ($q) => $q->where('branches.company_id', $companyId))
            ->when($branchId, fn ($q) => $q->where('employees.branch_id', $branchId))
            ->when($gender, fn ($q) => $q->where('employees.gender', $gender))
            ->when($birthMonth, fn ($q) => $q->whereRaw('MONTH(employees.birth_date) = ?', [$birthMonth]))
            ->when($status, fn ($q) => $q->where('employees.status', $status))
            ->orderBy('companies.name')
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->get();

        // Estadísticas globales
        $totalCount = $employees->count();
        $byGender = $employees->groupBy('gender')->map->count();
        $byStatus = $employees->groupBy('status')->map->count();
        $avgYears = $employees->whereNotNull('years_of_service')->avg('years_of_service');

        // Agrupación adaptativa
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

        $pdf = Pdf::loadView('pdf.employee-report', compact(
            'employees', 'groups', 'groupMode',
            'gender', 'birthMonth', 'status',
            'totalCount', 'byGender', 'byStatus', 'avgYears',
            'showCompanyHeader',
            'companyLogo', 'companyName', 'companyRuc', 'companyAddress',
            'companyPhone', 'companyEmail', 'city',
            'monthOptions', 'genderOptions', 'statusOptions',
        ))->setPaper('a4', 'landscape');

        $filename = 'reporte-empleados-'.now()->format('Ymd_His').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
