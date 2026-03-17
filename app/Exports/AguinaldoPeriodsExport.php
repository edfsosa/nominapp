<?php

namespace App\Exports;

use App\Models\AguinaldoPeriod;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AguinaldoPeriodsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected ?string $status = null,
    ) {}

    public function query()
    {
        return AguinaldoPeriod::with('company')
            ->withCount('aguinaldos')
            ->withCount(['aguinaldos as aguinaldos_paid_count' => fn($q) => $q->where('status', 'paid')])
            ->when($this->status, fn($q) => $q->where('status', $this->status))
            ->orderBy('year', 'desc')
            ->orderBy('company_id');
    }

    public function headings(): array
    {
        return [
            'Empresa',
            'Año',
            'Estado',
            'Generados',
            'Pagados',
            'Pendientes',
            'Monto Total (Gs.)',
            'Fecha de Cierre',
            'Creado',
        ];
    }

    public function map($period): array
    {
        return [
            $period->company->name,
            $period->year,
            AguinaldoPeriod::getStatusLabel($period->status),
            $period->aguinaldos_count,
            $period->aguinaldos_paid_count,
            $period->aguinaldos_count - $period->aguinaldos_paid_count,
            $period->aguinaldos()->sum('aguinaldo_amount'),
            $period->closed_at?->format('d/m/Y H:i') ?? '',
            $period->created_at->format('d/m/Y'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
