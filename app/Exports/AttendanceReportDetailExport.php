<?php

namespace App\Exports;

use App\Models\AttendanceDay;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta el detalle diario de asistencias para el período y filtros dados.
 *
 * Cada fila representa un día de asistencia de un empleado, con todos los
 * valores calculados: horas netas, horas extra, tardanza, estado, etc.
 */
class AttendanceReportDetailExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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

    /** @return array<string, string> */
    public static function availableColumns(): array
    {
        return [
            'employee_name' => 'Empleado',
            'ci' => 'CI',
            'branch_name' => 'Sucursal',
            'department_name' => 'Departamento',
            'position_name' => 'Cargo',
            'date' => 'Fecha',
            'status' => 'Estado',
            'check_in' => 'Entrada',
            'check_out' => 'Salida',
            'total_hours' => 'Horas Totales',
            'net_hours' => 'Horas Netas',
            'early_leave_minutes' => 'Salida Anticipada (min)',
            'break_minutes' => 'Descanso (min)',
            'justified_absence' => 'Ausencia Justificada',
            'anomaly_flag' => 'Anomalía',
        ];
    }

    /** @return array<string> */
    public static function defaultColumns(): array
    {
        return array_keys(static::availableColumns());
    }

    /**
     * Query base con un registro por día de asistencia, con relaciones eager-loaded.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return AttendanceDay::query()
            ->with([
                'employee',
                'employee.branch',
                'employee.activeContract.position.department',
            ])
            ->join('employees', 'employees.id', '=', 'attendance_days.employee_id')
            ->select('attendance_days.*')
            ->when($this->fromDate, fn ($q) => $q->whereDate('attendance_days.date', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('attendance_days.date', '<=', $this->toDate))
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
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('attendance_days.date');
    }

    /**
     * Encabezados de columna del archivo Excel.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_values(array_intersect_key(static::availableColumns(), array_flip($this->columns)));
    }

    /**
     * Mapea cada día de asistencia a una fila del Excel.
     *
     * @param  AttendanceDay  $day
     * @return array<int, mixed>
     */
    public function map($day): array
    {
        $employee = $day->employee;
        $contract = $employee?->activeContract;
        $position = $contract?->position;
        $department = $position?->department;

        $all = [
            'employee_name' => ($employee?->last_name.', '.$employee?->first_name) ?? '—',
            'ci' => $employee?->ci ?? '—',
            'branch_name' => $employee?->branch?->name ?? '—',
            'department_name' => $department?->name ?? '—',
            'position_name' => $position?->name ?? '—',
            'date' => $day->date?->format('d/m/Y') ?? '',
            'status' => AttendanceDay::getStatusLabel($day->status ?? 'absent'),
            'check_in' => $day->check_in_time ? \Carbon\Carbon::parse($day->check_in_time)->format('H:i') : '',
            'check_out' => $day->check_out_time ? \Carbon\Carbon::parse($day->check_out_time)->format('H:i') : '',
            'total_hours' => $day->total_hours ?? 0,
            'net_hours' => $day->net_hours ?? 0,
            'early_leave_minutes' => $day->early_leave_minutes ?? 0,
            'break_minutes' => $day->break_minutes ?? 0,
            'justified_absence' => AttendanceDay::formatBoolean((bool) $day->justified_absence),
            'anomaly_flag' => AttendanceDay::formatBoolean((bool) $day->anomaly_flag),
        ];

        return array_values(array_intersect_key($all, array_flip($this->columns)));
    }

    /**
     * Aplica estilos a la hoja de cálculo (encabezado en negrita).
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
