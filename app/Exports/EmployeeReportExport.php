<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta el reporte de empleados con fecha de ingreso, antigüedad, salario, cumpleaños y género.
 *
 * Los datos de contrato provienen del contrato activo del empleado.
 */
class EmployeeReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  int|null  $companyId  Filtrar por empresa.
     * @param  int|null  $branchId  Filtrar por sucursal.
     * @param  string|null  $gender  Filtrar por género ('masculino'|'femenino').
     * @param  int|null  $birthMonth  Filtrar por mes de cumpleaños (1–12).
     * @param  string|null  $status  Filtrar por estado ('active'|'inactive'|'suspended').
     */
    public function __construct(
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $gender = null,
        protected ?int $birthMonth = null,
        protected ?string $status = null,
    ) {}

    /**
     * Query base con filtros aplicados.
     */
    public function query(): Builder
    {
        return Employee::query()
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
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->gender, fn ($q) => $q->where('employees.gender', $this->gender))
            ->when($this->birthMonth, fn ($q) => $q->whereRaw('MONTH(employees.birth_date) = ?', [$this->birthMonth]))
            ->when($this->status, fn ($q) => $q->where('employees.status', $this->status))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name');
    }

    /**
     * Encabezados de columna del archivo Excel.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Empleado',
            'CI',
            'Género',
            'Fecha nacimiento',
            'Edad',
            'Mes cumpleaños',
            'Fecha ingreso',
            'Antigüedad (años)',
            'Salario (Gs.)',
            'Tipo salario',
            'Cargo',
            'Sucursal',
            'Empresa',
            'Estado',
            'Teléfono',
            'Email',
        ];
    }

    /**
     * Mapea cada empleado a una fila del Excel.
     *
     * @param  mixed  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $genderOptions = Employee::getGenderOptions();
        $statusOptions = Employee::getStatusOptions();
        $monthOptions = Employee::getMonthOptions();

        $salaryTypeLabels = ['mensual' => 'Mensual', 'jornal' => 'Jornal'];

        return [
            $row->last_name.', '.$row->first_name,
            $row->ci,
            $genderOptions[$row->gender] ?? '',
            $row->birth_date?->format('d/m/Y') ?? '',
            $row->birth_date ? $row->birth_date->age : '',
            $row->birth_date ? ($monthOptions[$row->birth_date->month] ?? '') : '',
            $row->hire_date ? \Carbon\Carbon::parse($row->hire_date)->format('d/m/Y') : '',
            $row->years_of_service ?? '',
            $row->salary ? (float) $row->salary : '',
            $salaryTypeLabels[$row->salary_type] ?? '',
            $row->position_name ?? '',
            $row->branch_name,
            $row->company_name,
            $statusOptions[$row->status] ?? $row->status,
            $row->phone ?? '',
            $row->email ?? '',
        ];
    }

    /**
     * Aplica estilos al encabezado de la hoja.
     *
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
