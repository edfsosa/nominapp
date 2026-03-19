<?php

namespace App\Exports;

use App\Models\Department;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class DepartmentsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected ?int $companyId = null,
    ) {}

    public function query()
    {
        return Department::with('company')
            ->withCount('positions')
            ->when($this->companyId, fn($q) => $q->where('company_id', $this->companyId))
            ->orderBy('company_id')
            ->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'Empresa',
            'Departamento',
            'Cargos',
            'Creado',
        ];
    }

    public function map($department): array
    {
        return [
            $department->company->trade_name,
            $department->name,
            $department->positions_count,
            $department->created_at->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
