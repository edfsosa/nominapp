<?php

namespace App\Exports;

use App\Models\AguinaldoPeriod;
use App\Services\AguinaldoService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta la provisión mensual de aguinaldo a Excel.
 *
 * Cada fila representa un empleado con su provisión acumulada hasta el mes indicado.
 */
class AguinaldoProvisionExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /** @var string[] Nombres de mes en español indexados por número (1-12). */
    private const MONTH_NAMES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function __construct(
        protected AguinaldoPeriod $period,
        protected int $upToMonth,
    ) {}

    public function collection()
    {
        return app(AguinaldoService::class)
            ->provisionQuery($this->period, $this->upToMonth)
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $monthName = self::MONTH_NAMES[$this->upToMonth] ?? $this->upToMonth;

        return [
            'CI',
            'Empleado',
            'Meses Trabajados',
            "Total Devengado a {$monthName} (Gs.)",
            'Provisión Aguinaldo (Gs.)',
        ];
    }

    /**
     * @param  \App\Models\Employee $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->ci,
            $row->first_name . ' ' . $row->last_name,
            $row->months_worked,
            number_format((float) $row->total_earned, 0, ',', '.'),
            number_format((float) $row->provision, 0, ',', '.'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
