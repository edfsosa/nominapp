<?php

namespace App\Exports;

use App\Models\AttendanceDay;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exporta registros de asistencia diaria (solo presentes) a Excel. */
class AttendanceDaysExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * @param  int[]|null    $employeeIds  Filtrar por uno o varios empleados.
     * @param  int[]|null    $branchIds    Filtrar por una o varias sucursales.
     * @param  string|null   $fromDate     Fecha de inicio (Y-m-d).
     * @param  string|null   $toDate       Fecha de fin (Y-m-d).
     */
    public function __construct(
        protected ?array  $employeeIds = null,
        protected ?array  $branchIds   = null,
        protected ?string $fromDate    = null,
        protected ?string $toDate      = null,
    ) {}

    /**
     * Query base: solo registros presentes, filtrada según los parámetros del constructor.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return AttendanceDay::query()
            ->where('status', 'present')
            ->with(['employee.branch'])
            ->when($this->employeeIds, fn($q) => $q->whereIn('employee_id', $this->employeeIds))
            ->when($this->branchIds,   fn($q) => $q->whereHas('employee', fn($q) => $q->whereIn('branch_id', $this->branchIds)))
            ->when($this->fromDate,    fn($q) => $q->whereDate('date', '>=', $this->fromDate))
            ->when($this->toDate,      fn($q) => $q->whereDate('date', '<=', $this->toDate))
            ->orderByDesc('date');
    }

    /**
     * Encabezados de columna del archivo Excel.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Fecha',
            'Empleado',
            'CI',
            'Sucursal',
            'Entrada',
            'Salida',
            'Horas trabajadas',
            'Horas netas',
            'Tardanza (min)',
            'Horas extra',
            'HE aprobadas',
            'Calculado',
        ];
    }

    /**
     * Mapea cada registro de asistencia a una fila del Excel.
     *
     * @param  AttendanceDay $day
     * @return array<int, mixed>
     */
    public function map($day): array
    {
        return [
            $day->date?->format('d/m/Y') ?? '',
            $day->employee?->full_name   ?? '—',
            $day->employee?->ci          ?? '—',
            $day->employee?->branch?->name ?? '—',
            $day->check_in_time          ?? '—',
            $day->check_out_time         ?? '—',
            $day->total_hours            ?? 0,
            $day->net_hours              ?? 0,
            $day->late_minutes           ?? 0,
            $day->extra_hours            ?? 0,
            $day->overtime_approved ? 'Sí' : 'No',
            $day->is_calculated ? 'Sí' : 'No',
        ];
    }

    /**
     * Aplica estilos a la hoja (encabezado en negrita).
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
