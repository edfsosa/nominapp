<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\Schedule;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exporta el listado de empleados con asignación vigente a un horario específico. */
class ScheduleEmployeesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * @param  int  $scheduleId  ID del horario a exportar
     */
    public function __construct(private int $scheduleId) {}

    /**
     * Query de empleados con asignación vigente hoy para el horario dado.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Schedule::find($this->scheduleId)
            ->currentEmployees()
            ->with(['activeContract.position.department', 'branch.company'])
            ->getQuery()
            ->addSelect([
                'employees.*',
                'employee_schedule_assignments.valid_from as assignment_start_date',
            ])
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
            'Apellido',
            'Nombre',
            'CI',
            'Cargo',
            'Departamento',
            'Sucursal',
            'Empresa',
            'Estado',
            'Asignado desde',
        ];
    }

    /**
     * Mapea cada empleado a una fila del Excel.
     *
     * @param  Employee  $employee
     * @return array<int, mixed>
     */
    public function map($employee): array
    {
        return [
            $employee->last_name,
            $employee->first_name,
            $employee->ci,
            $employee->activeContract?->position?->name ?? '—',
            $employee->activeContract?->position?->department?->name ?? '—',
            $employee->branch?->name ?? '—',
            $employee->branch?->company?->name ?? '—',
            $employee->status_label,
            $employee->assignment_start_date
                ? Carbon::parse($employee->assignment_start_date)->format('d/m/Y')
                : '—',
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
