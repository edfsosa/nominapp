<?php

namespace App\Exports;

use App\Models\Branch;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BranchesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(private ?int $companyId = null) {}

    public function query()
    {
        return Branch::withCount('activeEmployees')
            ->with('company')
            ->when($this->companyId, fn($q) => $q->where('company_id', $this->companyId))
            ->orderBy('name');
    }

    public function headings(): array
    {
        return $this->companyId
            ? ['Nombre', 'Ciudad', 'Dirección', 'Teléfono', 'Correo', 'Empleados Activos']
            : ['Empresa', 'Nombre', 'Ciudad', 'Dirección', 'Teléfono', 'Correo', 'Empleados Activos'];
    }

    public function map($branch): array
    {
        $row = $this->companyId ? [] : [$branch->company?->trade_name ?? $branch->company?->name ?? '—'];

        return array_merge($row, [
            $branch->name,
            $branch->city ?? '—',
            $branch->address ?? '—',
            $branch->phone ?? '—',
            $branch->email ?? '—',
            $branch->active_employees_count,
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
