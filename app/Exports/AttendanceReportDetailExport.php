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
class AttendanceReportDetailExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * @param  string|null $fromDate     Fecha de inicio del período (Y-m-d).
     * @param  string|null $toDate       Fecha de fin del período (Y-m-d).
     * @param  int|null    $companyId    Filtrar por empresa.
     * @param  int|null    $branchId     Filtrar por sucursal.
     * @param  int|null    $departmentId Filtrar por departamento (contrato activo).
     */
    public function __construct(
        protected ?string $fromDate     = null,
        protected ?string $toDate       = null,
        protected ?int    $companyId    = null,
        protected ?int    $branchId     = null,
        protected ?int    $departmentId = null,
    ) {}

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
            ->when($this->fromDate,     fn($q) => $q->whereDate('attendance_days.date', '>=', $this->fromDate))
            ->when($this->toDate,       fn($q) => $q->whereDate('attendance_days.date', '<=', $this->toDate))
            ->when($this->branchId,     fn($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->companyId,    fn($q) => $q->whereExists(fn($sub) => $sub->selectRaw(1)
                ->from('branches')
                ->whereColumn('branches.id', 'employees.branch_id')
                ->where('branches.company_id', $this->companyId)
            ))
            ->when($this->departmentId, fn($q) => $q->whereExists(fn($sub) => $sub->selectRaw(1)
                ->from('contracts')
                ->whereColumn('contracts.employee_id', 'employees.id')
                ->where('contracts.status', 'active')
                ->where('contracts.department_id', $this->departmentId)
            ))
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
            'Departamento',
            'Cargo',
            'Fecha',
            'Estado',
            'Entrada',
            'Salida',
            'Horas Totales',
            'Horas Netas',
            'Horas Extra',
            'HE Diurnas',
            'HE Nocturnas',
            'Tardanza (min)',
            'Salida Anticipada (min)',
            'Descanso (min)',
            'OT Aprobado',
            'Ausencia Justificada',
            'Anomalía',
        ];
    }

    /**
     * Mapea cada día de asistencia a una fila del Excel.
     *
     * @param  AttendanceDay $day
     * @return array<int, mixed>
     */
    public function map($day): array
    {
        $employee   = $day->employee;
        $contract   = $employee?->activeContract;
        $position   = $contract?->position;
        $department = $position?->department;

        return [
            ($employee?->last_name . ', ' . $employee?->first_name) ?? '—',
            $employee?->ci ?? '—',
            $employee?->branch?->name ?? '—',
            $department?->name ?? '—',
            $position?->name ?? '—',
            $day->date?->format('d/m/Y') ?? '',
            AttendanceDay::getStatusLabel($day->status ?? 'absent'),
            $day->check_in_time  ? \Carbon\Carbon::parse($day->check_in_time)->format('H:i')  : '',
            $day->check_out_time ? \Carbon\Carbon::parse($day->check_out_time)->format('H:i') : '',
            $day->total_hours ?? 0,
            $day->net_hours   ?? 0,
            $day->extra_hours ?? 0,
            $day->extra_hours_diurnas   ?? 0,
            $day->extra_hours_nocturnas ?? 0,
            $day->late_minutes        ?? 0,
            $day->early_leave_minutes ?? 0,
            $day->break_minutes       ?? 0,
            AttendanceDay::formatBoolean((bool) $day->overtime_approved),
            AttendanceDay::formatBoolean((bool) $day->justified_absence),
            AttendanceDay::formatBoolean((bool) $day->anomaly_flag),
        ];
    }

    /**
     * Aplica estilos a la hoja de cálculo (encabezado en negrita).
     *
     * @param  Worksheet $sheet
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
