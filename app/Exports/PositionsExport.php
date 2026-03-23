<?php

namespace App\Exports;

use App\Models\Position;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PositionsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected ?int $departmentId = null,
    ) {}

    public function query()
    {
        return Position::with(['department.company', 'parent'])
            ->withCount('employees')
            ->when($this->departmentId, fn($q) => $q->where('department_id', $this->departmentId))
            ->orderBy('department_id')
            ->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'Empresa',
            'Departamento',
            'Cargo',
            'Reporta a',
            'Empleados',
            'Creado',
        ];
    }

    public function map($position): array
    {
        return [
            $position->department?->company?->trade_name,
            $position->department?->name,
            $position->name,
            $position->parent?->name ?? '—',
            $position->employees_count,
            $position->created_at->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
