<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta un resumen de ausencias por empleado para el período y filtros dados.
 *
 * Cada fila representa un empleado con los totales agregados del período:
 * pendientes, justificadas, injustificadas y monto total de deducciones generadas.
 */
class AbsenceReportSummaryExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  string|null  $fromDate  Fecha de inicio del período (Y-m-d).
     * @param  string|null  $toDate  Fecha de fin del período (Y-m-d).
     * @param  int|null  $companyId  Filtrar por empresa.
     * @param  int|null  $branchId  Filtrar por sucursal.
     * @param  int|null  $departmentId  Filtrar por departamento (contrato activo).
     * @param  int|null  $employeeId  Filtrar por empleado específico.
     */
    public function __construct(
        protected ?string $fromDate = null,
        protected ?string $toDate = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?int $departmentId = null,
        protected ?int $employeeId = null,
        protected array $columns = [],
    ) {
        if (empty($this->columns)) {
            $this->columns = static::defaultColumns();
        }
    }

    /**
     * Todas las columnas disponibles: clave => label.
     *
     * @return array<string, string>
     */
    public static function availableColumns(): array
    {
        return [
            'employee_name' => 'Empleado',
            'ci' => 'CI',
            'branch_name' => 'Sucursal',
            'department_name' => 'Departamento',
            'position_name' => 'Cargo',
            'total_absences' => 'Total',
            'total_pending' => 'Pendientes',
            'total_justified' => 'Justificadas',
            'total_unjustified' => 'Injustificadas',
            'total_deduction_amount' => 'Deducciones Generadas (Gs.)',
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
     * Query base con métricas de ausencias agregadas por empleado.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Employee::query()
            ->select([
                'employees.id',
                'employees.last_name',
                'employees.first_name',
                'employees.ci',
                DB::raw('(SELECT b.name FROM branches b WHERE b.id = employees.branch_id) AS branch_name'),
                DB::raw("(SELECT d.name FROM contracts c INNER JOIN positions p ON p.id = c.position_id INNER JOIN departments d ON d.id = p.department_id WHERE c.employee_id = employees.id AND c.status = 'active' ORDER BY c.start_date DESC LIMIT 1) AS department_name"),
                DB::raw("(SELECT p.name FROM contracts c INNER JOIN positions p ON p.id = c.position_id WHERE c.employee_id = employees.id AND c.status = 'active' ORDER BY c.start_date DESC LIMIT 1) AS position_name"),
                DB::raw('COUNT(abs.id) AS total_absences'),
                DB::raw("COALESCE(SUM(CASE WHEN abs.status = 'pending'     THEN 1 ELSE 0 END), 0) AS total_pending"),
                DB::raw("COALESCE(SUM(CASE WHEN abs.status = 'justified'   THEN 1 ELSE 0 END), 0) AS total_justified"),
                DB::raw("COALESCE(SUM(CASE WHEN abs.status = 'unjustified' THEN 1 ELSE 0 END), 0) AS total_unjustified"),
                DB::raw('COALESCE(SUM(CASE WHEN abs.employee_deduction_id IS NOT NULL THEN ed.custom_amount ELSE 0 END), 0) AS total_deduction_amount'),
            ])
            ->join('absences as abs', 'abs.employee_id', '=', 'employees.id')
            ->join('attendance_days as ad', 'ad.id', '=', 'abs.attendance_day_id')
            ->leftJoin('employee_deductions as ed', 'ed.id', '=', 'abs.employee_deduction_id')
            ->when($this->fromDate, fn ($q) => $q->where('ad.date', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->where('ad.date', '<=', $this->toDate))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->companyId, fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw(1)
                ->from('branches')
                ->whereColumn('branches.id', 'employees.branch_id')
                ->where('branches.company_id', $this->companyId)
            ))
            ->when($this->departmentId, fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw(1)
                ->from('contracts')
                ->whereColumn('contracts.employee_id', 'employees.id')
                ->where('contracts.status', 'active')
                ->where('contracts.department_id', $this->departmentId)
            ))
            ->when($this->employeeId, fn ($q) => $q->where('employees.id', $this->employeeId))
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'employees.ci', 'employees.branch_id')
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
        $all = static::availableColumns();

        return array_values(array_intersect_key($all, array_flip($this->columns)));
    }

    /**
     * Mapea cada fila del resultado a una fila del Excel.
     *
     * @param  mixed  $employee
     * @return array<int, mixed>
     */
    public function map($employee): array
    {
        $all = [
            'employee_name' => $employee->last_name.', '.$employee->first_name,
            'ci' => $employee->ci,
            'branch_name' => $employee->branch_name ?? '—',
            'department_name' => $employee->department_name ?? '—',
            'position_name' => $employee->position_name ?? '—',
            'total_absences' => (int) $employee->total_absences,
            'total_pending' => (int) $employee->total_pending,
            'total_justified' => (int) $employee->total_justified,
            'total_unjustified' => (int) $employee->total_unjustified,
            'total_deduction_amount' => (float) $employee->total_deduction_amount,
        ];

        return array_values(array_intersect_key($all, array_flip($this->columns)));
    }

    /**
     * Aplica estilos al encabezado de la hoja de cálculo.
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
