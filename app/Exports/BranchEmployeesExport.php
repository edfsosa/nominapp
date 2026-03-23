<?php

namespace App\Exports;

use App\Models\Branch;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BranchEmployeesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private int $branchId) {}

    public function query()
    {
        return Branch::find($this->branchId)
            ->employees()
            ->with('activeContract.position.department')
            ->getQuery()
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    public function headings(): array
    {
        return [
            'Apellido',
            'Nombre',
            'CI',
            'Cargo',
            'Departamento',
            'Estado',
            'Teléfono',
        ];
    }

    public function map($employee): array
    {
        return [
            $employee->last_name,
            $employee->first_name,
            $employee->ci,
            $employee->activeContract?->position?->name ?? '—',
            $employee->activeContract?->position?->department?->name ?? '—',
            match ($employee->status) {
                'active'    => 'Activo',
                'inactive'  => 'Inactivo',
                'suspended' => 'Suspendido',
                default     => $employee->status,
            },
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
