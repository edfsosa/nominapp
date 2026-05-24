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
 * Exporta un resumen de horas extras y tardanzas por empleado para el período dado.
 *
 * Cada fila representa un empleado con los totales agregados del período:
 * horas extras diurnas/nocturnas, días con extras, tardanza total, días con tardanza.
 */
class OvertimeReportSummaryExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
    ) {}

    /**
     * Query base con métricas de horas extras y tardanzas agregadas por empleado.
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
                DB::raw('ROUND(COALESCE(SUM(ad.extra_hours), 0), 2)             AS total_extra_hours'),
                DB::raw('ROUND(COALESCE(SUM(ad.extra_hours_diurnas), 0), 2)     AS total_extra_diurnas'),
                DB::raw('ROUND(COALESCE(SUM(ad.extra_hours_nocturnas), 0), 2)   AS total_extra_nocturnas'),
                DB::raw('COALESCE(SUM(CASE WHEN ad.extra_hours > 0 THEN 1 ELSE 0 END), 0)        AS days_with_extras'),
                DB::raw('COALESCE(SUM(CASE WHEN ad.overtime_approved = 1 THEN 1 ELSE 0 END), 0)  AS days_approved'),
                DB::raw('COALESCE(SUM(ad.late_minutes), 0)                                        AS total_late_minutes'),
                DB::raw('COALESCE(SUM(CASE WHEN ad.late_minutes > 0 THEN 1 ELSE 0 END), 0)       AS days_late'),
                DB::raw('ROUND(COALESCE(AVG(CASE WHEN ad.late_minutes > 0 THEN ad.late_minutes END), 0), 0) AS avg_late_minutes'),
            ])
            ->join('attendance_days as ad', 'ad.employee_id', '=', 'employees.id')
            ->where(fn ($q) => $q->where('ad.extra_hours', '>', 0)->orWhere('ad.late_minutes', '>', 0))
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
        return [
            'Empleado',
            'CI',
            'Sucursal',
            'Departamento',
            'Cargo',
            'HE Total (h)',
            'HE Diurnas (h)',
            'HE Nocturnas (h)',
            'Días con HE',
            'Días HE Aprobados',
            'Tardanza Total (min)',
            'Días con Tardanza',
            'Tardanza Promedio (min)',
        ];
    }

    /**
     * Mapea cada empleado a una fila del Excel.
     *
     * @param  mixed  $employee
     * @return array<int, mixed>
     */
    public function map($employee): array
    {
        return [
            $employee->last_name.', '.$employee->first_name,
            $employee->ci,
            $employee->branch_name ?? '—',
            $employee->department_name ?? '—',
            $employee->position_name ?? '—',
            (float) $employee->total_extra_hours,
            (float) $employee->total_extra_diurnas,
            (float) $employee->total_extra_nocturnas,
            (int) $employee->days_with_extras,
            (int) $employee->days_approved,
            (int) $employee->total_late_minutes,
            (int) $employee->days_late,
            (int) $employee->avg_late_minutes,
        ];
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
