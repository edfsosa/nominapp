<?php

namespace App\Exports;

use App\Models\Payroll;
use App\Models\PayrollItem;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta el reporte de salarios a Excel.
 *
 * Cada fila representa un recibo de nómina con desglose de deducciones por tipo.
 * Incluye fila de totales al final y nombre de hoja descriptivo.
 */
class SalaryReportExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @var array<string, float> Totales pre-calculados para la fila de cierre. */
    protected array $totals;

    /**
     * @param  int|null  $periodId  Filtrar por planilla (null = todas).
     * @param  int|null  $companyId  Filtrar por empresa (null = todas).
     * @param  int|null  $branchId  Filtrar por sucursal (null = todas).
     * @param  string|null  $status  Filtrar por estado (null = todos).
     * @param  string|null  $paymentMethod  Filtrar por método de pago (null = todos).
     */
    public function __construct(
        protected ?int $periodId = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $status = null,
        protected ?string $paymentMethod = null,
    ) {
        $this->totals = $this->computeTotals();
    }

    public function title(): string
    {
        return 'Reporte de Salarios';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Payroll::query()
            ->select([
                'payrolls.id',
                'payrolls.base_salary',
                'payrolls.total_perceptions',
                'payrolls.total_deductions',
                'payrolls.net_salary',
                'payrolls.status',
                'payrolls.payment_method',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.name as company_name',
                \DB::raw('(SELECT positions.name FROM contracts INNER JOIN positions ON positions.id = contracts.position_id WHERE contracts.employee_id = employees.id AND contracts.status = \'active\' LIMIT 1) as position_name'),
                \DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'legal\') as ips_amount'),
                \DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'loan\') as loan_amount'),
                \DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'judicial\') as judicial_amount'),
                \DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'voluntary\') as voluntary_amount'),
            ])
            ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->join('companies', 'companies.id', '=', 'branches.company_id')
            ->when($this->periodId, fn ($q) => $q->where('payrolls.payroll_period_id', $this->periodId))
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->status, fn ($q) => $q->where('payrolls.status', $this->status))
            ->when($this->paymentMethod, fn ($q) => $q->where('payrolls.payment_method', $this->paymentMethod))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name');
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Empleado',
            'CI',
            'Sucursal',
            'Cargo',
            'Salario Base (Gs.)',
            '+Percepciones (Gs.)',
            'IPS (Gs.)',
            'Desc. por Deuda (Gs.)',
            'Judiciales (Gs.)',
            'Voluntarias (Gs.)',
            '-Deducciones Total (Gs.)',
            'Neto a Pagar (Gs.)',
            'Método de Pago',
            'Estado',
        ];
    }

    /**
     * @param  mixed  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->last_name.', '.$row->first_name,
            $row->ci,
            $row->branch_name,
            $row->position_name ?? '',
            (float) $row->base_salary,
            (float) $row->total_perceptions,
            (float) $row->ips_amount,
            (float) $row->loan_amount,
            (float) $row->judicial_amount,
            (float) $row->voluntary_amount,
            (float) $row->total_deductions,
            (float) $row->net_salary,
            Payroll::getPaymentMethodLabels()[$row->payment_method] ?? ($row->payment_method ?? ''),
            Payroll::getStatusLabels()[$row->status] ?? $row->status,
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * Agrega la fila de totales al final del sheet con estilos diferenciados.
     *
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $row = $sheet->getHighestRow() + 1;

                $sheet->setCellValue('A'.$row, 'TOTALES');
                $sheet->setCellValue('E'.$row, (float) $this->totals['total_base']);
                $sheet->setCellValue('F'.$row, (float) $this->totals['total_perceptions']);
                $sheet->setCellValue('G'.$row, (float) $this->totals['total_ips']);
                $sheet->setCellValue('H'.$row, (float) $this->totals['total_loans']);
                $sheet->setCellValue('I'.$row, (float) $this->totals['total_judicial']);
                $sheet->setCellValue('J'.$row, (float) $this->totals['total_voluntary']);
                $sheet->setCellValue('K'.$row, (float) $this->totals['total_deductions']);
                $sheet->setCellValue('L'.$row, (float) $this->totals['total_net']);

                $sheet->getStyle('A'.$row.':N'.$row)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F0F0F0'],
                    ],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN],
                    ],
                ]);
            },
        ];
    }

    /**
     * Calcula los totales agregados aplicando los mismos filtros que la query principal.
     *
     * @return array<string, float>
     */
    private function computeTotals(): array
    {
        $payrollIds = Payroll::query()
            ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->when($this->periodId, fn ($q) => $q->where('payrolls.payroll_period_id', $this->periodId))
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->status, fn ($q) => $q->where('payrolls.status', $this->status))
            ->when($this->paymentMethod, fn ($q) => $q->where('payrolls.payment_method', $this->paymentMethod))
            ->pluck('payrolls.id');

        $base = Payroll::whereIn('id', $payrollIds)
            ->selectRaw('
                COALESCE(SUM(base_salary), 0) as total_base,
                COALESCE(SUM(total_perceptions), 0) as total_perceptions,
                COALESCE(SUM(total_deductions), 0) as total_deductions,
                COALESCE(SUM(net_salary), 0) as total_net
            ')
            ->first();

        $items = PayrollItem::whereIn('payroll_id', $payrollIds)
            ->where('type', 'deduction')
            ->selectRaw('deduction_type, COALESCE(SUM(amount), 0) as total')
            ->groupBy('deduction_type')
            ->pluck('total', 'deduction_type');

        return [
            'total_base'        => (float) $base->total_base,
            'total_perceptions' => (float) $base->total_perceptions,
            'total_ips'         => (float) ($items['legal'] ?? 0),
            'total_loans'       => (float) ($items['loan'] ?? 0),
            'total_judicial'    => (float) ($items['judicial'] ?? 0),
            'total_voluntary'   => (float) ($items['voluntary'] ?? 0),
            'total_deductions'  => (float) $base->total_deductions,
            'total_net'         => (float) $base->total_net,
        ];
    }
}
