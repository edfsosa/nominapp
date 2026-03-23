<?php

namespace App\Exports;

use App\Models\Company;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompaniesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function query()
    {
        return Company::withCount(['branches', 'employees'])
            ->orderBy('name');
    }

    public function headings(): array
    {
        return [
            'Razón Social',
            'Nombre Comercial',
            'RUC',
            'Nro. Patronal IPS',
            'Tipo Societario',
            'Sucursales',
            'Empleados',
            'Estado',
            'Creado',
        ];
    }

    public function map($company): array
    {
        return [
            $company->name,
            $company->trade_name ?? '—',
            $company->ruc,
            $company->employer_number,
            $company->legal_type_label ?? '—',
            $company->branches_count,
            $company->employees_count,
            $company->is_active ? 'Activa' : 'Inactiva',
            $company->created_at->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
