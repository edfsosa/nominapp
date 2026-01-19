<?php

namespace App\Exports;

use App\Models\LoanInstallment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Clase para exportar las cuotas vencidas a un archivo Excel
 */
class OverdueInstallmentsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
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
     *
     * @return array
     */
    public function headings(): array
    {
        return [
            'Empleado',
            'CI',
            'Tipo',
            'Cuota',
            'Monto (Gs.)',
            'Fecha Vencimiento',
            'Dias de Atraso',
        ];
    }

    /**
     * Mapea cada cuota vencida para la exportación
     *
     * @param  mixed  $installment
     * @return array
     */
    public function map($installment): array
    {
        return [
            $installment->loan->employee->full_name,
            $installment->loan->employee->ci,
            $installment->loan->type === 'loan' ? 'Prestamo' : 'Adelanto',
            "{$installment->installment_number}/{$installment->loan->installments_count}",
            $installment->amount,
            $installment->due_date->format('d/m/Y'),
            $installment->due_date->diffInDays(now()),
        ];
    }

    /**
     * Aplica estilos a la hoja de cálculo
     *
     * @param  Worksheet  $sheet
     * @return array
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
