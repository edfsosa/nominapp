<?php

namespace App\Exports;

use App\Models\LoanInstallment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Clase para exportar las cuotas vencidas a un archivo Excel
 */
class OverdueInstallmentsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * Obtiene la colección de cuotas vencidas para exportar
     *
     * @return void
     */
    public function collection()
    {
        return LoanInstallment::overdue()
            ->with(['loan.employee'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Obtiene los encabezados para la exportación
     */
    public function headings(): array
    {
        return [
            'Empleado',
            'CI',
            'Cuota',
            'Monto (Gs.)',
            'Fecha Vencimiento',
            'Días de Atraso',
        ];
    }

    /**
     * Mapea cada cuota vencida para la exportación
     *
     * @param  mixed  $installment
     */
    public function map($installment): array
    {
        return [
            $installment->loan->employee->full_name,
            $installment->loan->employee->ci,
            "{$installment->installment_number}/{$installment->loan->installments_count}",
            $installment->amount,
            $installment->due_date->format('d/m/Y'),
            $installment->due_date->diffInDays(now()),
        ];
    }

    /**
     * Aplica estilos a la hoja de cálculo
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
