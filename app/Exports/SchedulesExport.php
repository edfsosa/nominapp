<?php

namespace App\Exports;

use App\Models\Schedule;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exporta el listado de horarios con sus días activos y empleados asignados. */
class SchedulesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * Query base con relaciones necesarias para el mapeo.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Schedule::withCount(['activeDays', 'currentEmployees'])->orderBy('name');
    }

    /**
     * Encabezados de columna del archivo Excel.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Nombre',
            'Tipo de Jornada',
            'Días Activos',
            'Empleados Asignados',
            'Descripción',
            'Creado',
        ];
    }

    /**
     * Mapea cada horario a una fila del Excel.
     *
     * @param  Schedule  $schedule
     * @return array<int, mixed>
     */
    public function map($schedule): array
    {
        return [
            $schedule->name,
            Schedule::getShiftTypeLabels()[$schedule->shift_type] ?? $schedule->shift_type,
            $schedule->active_days_count,
            $schedule->current_employees_count,
            $schedule->description ?? '—',
            $schedule->created_at->format('d/m/Y'),
        ];
    }

    /**
     * Aplica negrita a la fila de encabezados.
     *
     * @param  Worksheet  $sheet
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
