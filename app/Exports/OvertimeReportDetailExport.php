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
 * Exporta el detalle diario de horas extras y tardanzas para el período dado.
 *
 * Solo incluye días donde el empleado registró horas extras o llegada tarde.
 * Cada fila representa un día de un empleado con los valores desagregados.
 */
class OvertimeReportDetailExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
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
     * Query base: días con horas extras o tardanza, con relaciones eager-loaded.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return AttendanceDay::query()
            ->with(['employee', 'employee.branch'])
            ->join('employees', 'employees.id', '=', 'attendance_days.employee_id')
            ->select('attendance_days.*')
            ->where(fn ($q) => $q->where('attendance_days.extra_hours', '>', 0)
                ->orWhere('attendance_days.late_minutes', '>', 0)
            )
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
        return [
            'Empleado',
            'CI',
            'Sucursal',
            'Fecha',
            'Día',
            'Entrada Esperada',
            'Entrada Real',
            'Tardanza (min)',
            'HE Total (h)',
            'HE Diurnas (h)',
            'HE Nocturnas (h)',
            'HE Aprobada',
            'Límite Excedido',
            'Trabajo Extraordinario',
            'Feriado',
        ];
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

        $dayNames = [
            'Monday' => 'Lunes',
            'Tuesday' => 'Martes',
            'Wednesday' => 'Miércoles',
            'Thursday' => 'Jueves',
            'Friday' => 'Viernes',
            'Saturday' => 'Sábado',
            'Sunday' => 'Domingo',
        ];

        return [
            ($employee?->last_name.', '.$employee?->first_name) ?? '—',
            $employee?->ci ?? '—',
            $employee?->branch?->name ?? '—',
            $day->date?->format('d/m/Y') ?? '',
            $day->date ? ($dayNames[$day->date->format('l')] ?? $day->date->format('l')) : '',
            $day->expected_check_in ? \Carbon\Carbon::parse($day->expected_check_in)->format('H:i') : '',
            $day->check_in_time ? \Carbon\Carbon::parse($day->check_in_time)->format('H:i') : '',
            (int) ($day->late_minutes ?? 0),
            (float) ($day->extra_hours ?? 0),
            (float) ($day->extra_hours_diurnas ?? 0),
            (float) ($day->extra_hours_nocturnas ?? 0),
            AttendanceDay::formatBoolean((bool) $day->overtime_approved),
            AttendanceDay::formatBoolean((bool) $day->overtime_limit_exceeded),
            AttendanceDay::formatBoolean((bool) $day->is_extraordinary_work),
            AttendanceDay::formatBoolean((bool) $day->is_holiday),
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
