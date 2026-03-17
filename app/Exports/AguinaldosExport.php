<?php

namespace App\Exports;

use App\Models\Aguinaldo;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AguinaldosExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected ?int $periodId = null,
        protected ?string $status = null,
    ) {}

    public function query()
    {
        return Aguinaldo::with(['employee', 'period.company'])
            ->when($this->periodId, fn($q) => $q->where('aguinaldo_period_id', $this->periodId))
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->orderBy('aguinaldo_amount', 'desc');
    }

    public function headings(): array
    {
        return [
            'CI',
            'Empleado',
            'Empresa',
            'Año',
            'Estado',
            'Meses Trabajados',
            'Total Devengado (Gs.)',
            'Aguinaldo (Gs.)',
            'Fecha de Pago',
            'Generado',
        ];
    }

    public function map($aguinaldo): array
    {
        return [
            $aguinaldo->employee->ci,
            $aguinaldo->employee->full_name,
            $aguinaldo->period->company->name,
            $aguinaldo->period->year,
            Aguinaldo::getStatusLabel($aguinaldo->status),
            number_format($aguinaldo->months_worked, 0),
            $aguinaldo->total_earned,
            $aguinaldo->aguinaldo_amount,
            $aguinaldo->paid_at?->format('d/m/Y') ?? '',
            $aguinaldo->generated_at?->format('d/m/Y H:i') ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
