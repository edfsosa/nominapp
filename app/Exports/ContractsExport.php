<?php

namespace App\Exports;

use App\Models\Contract;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exporta el listado de contratos con sus datos laborales y de empleado. */
class ContractsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * Query base con relaciones necesarias para el mapeo.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Contract::with(['employee', 'position', 'department'])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Encabezados de columna del archivo Excel.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Empleado',
            'CI',
            'Tipo',
            'Estado',
            'Fecha Inicio',
            'Fecha Fin',
            'Días de Prueba',
            'Salario (Gs.)',
            'Tipo de Remuneración',
            'Cargo',
            'Departamento',
            'Modalidad',
            'Tipo de Nómina',
        ];
    }

    /**
     * Mapea cada contrato a una fila del Excel.
     *
     * @param  Contract $contract
     * @return array<int, mixed>
     */
    public function map($contract): array
    {
        return [
            $contract->employee->full_name,
            $contract->employee->ci,
            Contract::getTypeLabel($contract->type),
            Contract::getStatusLabel($contract->status),
            $contract->start_date?->format('d/m/Y') ?? '',
            $contract->end_date?->format('d/m/Y') ?? 'Indefinido',
            $contract->trial_days ?? 0,
            $contract->salary,
            Contract::getSalaryTypeLabel($contract->salary_type),
            $contract->position?->name ?? '—',
            $contract->department?->name ?? '—',
            Contract::getWorkModalityLabel($contract->work_modality),
            $contract->payroll_type ?? '—',
        ];
    }

    /**
     * Aplica estilos a la hoja de cálculo (encabezado en negrita).
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
