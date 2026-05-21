<?php

namespace App\Exports;

use App\Models\Contract;
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
 * Exporta el reporte de empleados con columnas seleccionables por el usuario.
 *
 * Los datos de contrato provienen del contrato activo del empleado.
 */
class EmployeeReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  array<string>  $columns  Claves de columnas a incluir (ver availableColumns())
     */
    public function __construct(
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $gender = null,
        protected ?int $birthMonth = null,
        protected ?string $status = null,
        protected ?int $departmentId = null,
        protected ?string $contractType = null,
        protected ?string $paymentMethod = null,
        protected array $columns = [],
    ) {
        if (empty($this->columns)) {
            $this->columns = array_keys(static::availableColumns());
        }
    }

    /**
     * Todas las columnas disponibles para el export: clave => label.
     *
     * @return array<string, string>
     */
    public static function availableColumns(): array
    {
        return [
            'employee_name' => 'Empleado',
            'ci' => 'CI',
            'gender' => 'Género',
            'age' => 'Edad',
            'birthday' => 'Cumpleaños',
            'hire_date' => 'Fecha ingreso',
            'years_of_service' => 'Antigüedad (años)',
            'salary' => 'Salario (Gs.)',
            'contract_type' => 'Tipo de contrato',
            'payment_method' => 'Método de pago',
            'position_name' => 'Cargo',
            'department_name' => 'Departamento',
            'branch_name' => 'Sucursal',
            'company_name' => 'Empresa',
            'status' => 'Estado',
            'phone' => 'Teléfono',
            'email' => 'Email',
        ];
    }

    /**
     * Columnas seleccionadas por defecto (todas).
     *
     * @return array<string>
     */
    public static function defaultColumns(): array
    {
        return array_keys(static::availableColumns());
    }

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
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->gender, fn ($q) => $q->where('employees.gender', $this->gender))
            ->when($this->birthMonth, fn ($q) => $q->whereRaw('MONTH(employees.birth_date) = ?', [$this->birthMonth]))
            ->when($this->status, fn ($q) => $q->where('employees.status', $this->status))
            ->when($this->departmentId, fn ($q) => $q->where('contracts.department_id', $this->departmentId))
            ->when($this->contractType, fn ($q) => $q->where('contracts.type', $this->contractType))
            ->when($this->paymentMethod, fn ($q) => $q->where('contracts.payment_method', $this->paymentMethod))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name');
    }

    /**
     * Encabezados de columna según la selección del usuario.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        $all = static::availableColumns();

        return array_values(array_intersect_key($all, array_flip($this->columns)));
    }

    /**
     * Mapea cada empleado a una fila del Excel con solo las columnas seleccionadas.
     *
     * @param  mixed  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $genderOptions = Employee::getGenderOptions();
        $statusOptions = Employee::getStatusOptions();
        $contractTypes = Contract::getTypeOptions();
        $paymentMethods = Employee::getPaymentMethodOptions();

        $all = [
            'employee_name' => $row->last_name.', '.$row->first_name,
            'ci' => $row->ci,
            'gender' => $genderOptions[$row->gender] ?? '',
            'age' => $row->birth_date ? $row->birth_date->age : '',
            'birthday' => $row->birth_date ? $row->birth_date->day.' de '.$row->birth_date->locale('es')->isoFormat('MMMM') : '',
            'hire_date' => $row->hire_date ? \Carbon\Carbon::parse($row->hire_date)->format('d/m/Y') : '',
            'years_of_service' => $row->years_of_service ?? '',
            'salary' => $row->salary ? (float) $row->salary : '',
            'contract_type' => $contractTypes[$row->contract_type] ?? '',
            'payment_method' => $paymentMethods[$row->payment_method] ?? '',
            'position_name' => $row->position_name ?? '',
            'department_name' => $row->department_name ?? '',
            'branch_name' => $row->branch_name,
            'company_name' => $row->company_name,
            'status' => $statusOptions[$row->status] ?? $row->status,
            'phone' => $row->phone ?? '',
            'email' => $row->email ?? '',
        ];

        return array_values(array_intersect_key($all, array_flip($this->columns)));
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
