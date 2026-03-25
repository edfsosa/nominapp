<?php

namespace App\Exports;

use App\Models\AttendanceEvent;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exporta marcaciones de asistencia a Excel respetando los filtros activos de la tabla. */
class AttendanceEventsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * @param  int[]|null    $employeeIds  Filtrar por uno o varios empleados.
     * @param  int[]|null    $branchIds    Filtrar por una o varias sucursales.
     * @param  string[]|null $eventTypes   Filtrar por uno o varios tipos de evento.
     * @param  string|null   $fromDate     Fecha de inicio (Y-m-d).
     * @param  string|null   $toDate       Fecha de fin (Y-m-d).
     * @param  bool          $onlyToday    Restringir a las marcaciones del día de hoy.
     */
    public function __construct(
        protected ?array  $employeeIds = null,
        protected ?array  $branchIds   = null,
        protected ?array  $eventTypes  = null,
        protected ?string $fromDate    = null,
        protected ?string $toDate      = null,
        protected bool    $onlyToday   = false,
    ) {}

    /**
     * Query base filtrada según los parámetros del constructor.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return AttendanceEvent::query()
            ->when($this->employeeIds, fn($q) => $q->whereIn('employee_id', $this->employeeIds))
            ->when($this->branchIds,   fn($q) => $q->whereIn('branch_id',   $this->branchIds))
            ->when($this->eventTypes,  fn($q) => $q->whereIn('event_type',  $this->eventTypes))
            ->when($this->fromDate,    fn($q) => $q->whereDate('recorded_at', '>=', $this->fromDate))
            ->when($this->toDate,      fn($q) => $q->whereDate('recorded_at', '<=', $this->toDate))
            ->when($this->onlyToday,   fn($q) => $q->whereDate('recorded_at', today()))
            ->orderByDesc('recorded_at');
    }

    /**
     * Encabezados de columna del archivo Excel.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Fecha y Hora',
            'Tipo de Evento',
            'Empleado',
            'CI',
            'Sucursal',
            'Ubicación',
        ];
    }

    /**
     * Mapea cada marcación a una fila del Excel.
     *
     * @param  AttendanceEvent $event
     * @return array<int, mixed>
     */
    public function map($event): array
    {
        return [
            $event->recorded_at?->format('d/m/Y H:i:s') ?? '',
            AttendanceEvent::getEventTypeLabel($event->event_type),
            $event->employee_name ?? '—',
            $event->employee_ci   ?? '—',
            $event->branch_name   ?? '—',
            $event->location_display ?? '—',
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
