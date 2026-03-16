<?php

namespace App\Exports;

use App\Models\Loan;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LoansExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected array|null $status = null,
        protected ?string $type = null,
    ) {}

    public function query()
    {
        return Loan::with(['employee', 'installments'])
            ->when($this->status, fn($q) => $q->whereIn('status', $this->status))
            ->when($this->type,   fn($q) => $q->where('type',   $this->type))
            ->orderBy('created_at', 'desc');
    }

    public function headings(): array
    {
        return [
            'Empleado',
            'CI',
            'Tipo',
            'Estado',
            'Monto Total (Gs.)',
            'Monto Cuota (Gs.)',
            'Cuotas Total',
            'Cuotas Pagadas',
            'Saldo Pendiente (Gs.)',
            'Motivo',
            'Fecha Activación',
        ];
    }

    public function map($loan): array
    {
        return [
            $loan->employee->full_name,
            $loan->employee->ci,
            $loan->type_label,
            $loan->status_label,
            $loan->amount,
            $loan->installment_amount,
            $loan->installments_count,
            $loan->paid_installments_count,
            $loan->pending_amount,
            $loan->reason ?? '',
            $loan->granted_at?->format('d/m/Y') ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
