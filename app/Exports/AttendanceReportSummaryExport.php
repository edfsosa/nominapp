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
 * Exporta un resumen de asistencias por empleado para el período y filtros dados.
 *
 * Cada fila representa un empleado con los totales agregados del período:
 * días presentes, ausencias, licencias, horas netas, horas extra y tardanza.
 */
class AttendanceReportSummaryExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
            'days_present' => 'Presentes',
            'days_absent' => 'Ausencias',
            'days_leave' => 'Licencias',
            'days_non_working' => 'No laborables',
            'total_net_hours' => 'Horas Netas',
            'total_anomalies' => 'Anomalías',
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
     * Query base con métricas de asistencia agregadas por empleado.
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
                DB::raw("COALESCE(SUM(CASE WHEN ad.status = 'present'  THEN 1 ELSE 0 END), 0) AS days_present"),
                DB::raw("COALESCE(SUM(CASE WHEN ad.status = 'absent'   THEN 1 ELSE 0 END), 0) AS days_absent"),
                DB::raw("COALESCE(SUM(CASE WHEN ad.status = 'on_leave' THEN 1 ELSE 0 END), 0) AS days_leave"),
                DB::raw("COALESCE(SUM(CASE WHEN ad.status IN ('holiday','weekend') THEN 1 ELSE 0 END), 0) AS days_non_working"),
                DB::raw('ROUND(COALESCE(SUM(ad.net_hours), 0), 2)             AS total_net_hours'),
                DB::raw('COALESCE(SUM(CASE WHEN ad.anomaly_flag = 1 THEN 1 ELSE 0 END), 0) AS total_anomalies'),
            ])
            ->join('attendance_days as ad', 'ad.employee_id', '=', 'employees.id')
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
            'days_present' => (int) $employee->days_present,
            'days_absent' => (int) $employee->days_absent,
            'days_leave' => (int) $employee->days_leave,
            'days_non_working' => (int) $employee->days_non_working,
            'total_net_hours' => (float) $employee->total_net_hours,
            'total_anomalies' => (int) $employee->total_anomalies,
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
