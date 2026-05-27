<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exporta a Excel los empleados con contrato activo en un cargo específico. */
class PositionEmployeesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private int $positionId) {}

    public function query(): Builder
    {
        return Employee::query()
            ->whereHas('activeContract', fn ($q) => $q->where('position_id', $this->positionId))
            ->with('branch')
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['Apellido', 'Nombre', 'CI', 'Sucursal', 'Estado', 'Teléfono'];
    }

    /**
     * @param  Employee  $employee
     * @return array<int, mixed>
     */
    public function map($employee): array
    {
        return [
            $employee->last_name,
            $employee->first_name,
            $employee->ci,
            $employee->branch?->name ?? '—',
            Employee::getStatusOptions()[$employee->status] ?? $employee->status,
            $employee->phone ?? '—',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
