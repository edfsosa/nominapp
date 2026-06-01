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
 * Exporta el reporte de salarios a Excel con columnas seleccionables.
 *
 * Incluye fila de totales al final para las columnas monetarias seleccionadas.
 * Las posiciones de columna Excel se calculan dinámicamente según la selección.
 */
class SalaryReportExport implements FromQuery, ShouldAutoSize, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /** @var array<string, float> Totales pre-calculados para la fila de cierre. */
    protected array $totals;

    /** @var array<string, string> Mapa de campo => letra de columna Excel (A, B, C...) */
    protected array $fieldToExcelCol;

    /** @var int Número de columnas seleccionadas (determina el rango del estilo de totales). */
    protected int $colCount;

    /**
     * @param  int|null  $periodId  Filtrar por planilla (null = todas).
     * @param  int|null  $companyId  Filtrar por empresa (null = todas).
     * @param  int|null  $branchId  Filtrar por sucursal (null = todas).
     * @param  string|null  $status  Filtrar por estado (null = todos).
     * @param  string|null  $paymentMethod  Filtrar por método de pago (null = todos).
     * @param  array<string>  $columns  Claves de columnas a incluir (ver availableColumns()).
     */
    public function __construct(
        protected ?int $periodId = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $status = null,
        protected ?string $paymentMethod = null,
        protected array $columns = [],
    ) {
        if (empty($this->columns)) {
            $this->columns = static::defaultColumns();
        }

        $this->buildColMap();
        $this->totals = $this->computeTotals();
    }

    public function title(): string
    {
        return 'Reporte de Salarios';
    }

    /**
     * Todas las columnas disponibles para el export: clave => label.
     *
     * @return array<string, string>
     */
    public static function availableColumns(): array
    {
        return [
            'employee_name' => 'Empleado',
            'ci' => 'CI',
            'branch_name' => 'Sucursal',
            'position_name' => 'Cargo',
            'base_salary' => 'Salario Base (Gs.)',
            'total_perceptions' => '+Percepciones (Gs.)',
            'ips_amount' => 'IPS (Gs.)',
            'loan_amount' => 'Desc. por Deuda (Gs.)',
            'judicial_amount' => 'Judiciales (Gs.)',
            'voluntary_amount' => 'Voluntarias (Gs.)',
            'total_deductions' => '-Deducciones Total (Gs.)',
            'net_salary' => 'Neto a Pagar (Gs.)',
            'payment_method' => 'Método de Pago',
            'status' => 'Estado',
        ];
    }

    /**
     * Columnas seleccionadas por defecto (todas).
     *
     * @return array<string>
     */
    public static function defaultColumns(): array
    {
        return array_keys(static::availableColumns());
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
     * Encabezados filtrados según las columnas seleccionadas.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        $all = static::availableColumns();

        return array_values(array_intersect_key($all, array_flip($this->columns)));
    }

    /**
     * Mapea cada registro a una fila del Excel con solo las columnas seleccionadas.
     *
     * @param  mixed  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $all = [
            'employee_name' => $row->last_name.', '.$row->first_name,
            'ci' => $row->ci,
            'branch_name' => $row->branch_name,
            'position_name' => $row->position_name ?? '',
            'base_salary' => (float) $row->base_salary,
            'total_perceptions' => (float) $row->total_perceptions,
            'ips_amount' => (float) $row->ips_amount,
            'loan_amount' => (float) $row->loan_amount,
            'judicial_amount' => (float) $row->judicial_amount,
            'voluntary_amount' => (float) $row->voluntary_amount,
            'total_deductions' => (float) $row->total_deductions,
            'net_salary' => (float) $row->net_salary,
            'payment_method' => Payroll::getPaymentMethodLabels()[$row->payment_method] ?? ($row->payment_method ?? ''),
            'status' => Payroll::getStatusLabels()[$row->status] ?? $row->status,
        ];

        return array_values(array_intersect_key($all, array_flip($this->columns)));
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
     * Agrega la fila de totales al final del sheet en las columnas monetarias seleccionadas.
     *
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $row = $sheet->getHighestRow() + 1;

                $monetaryFieldToTotal = [
                    'base_salary' => 'total_base',
                    'total_perceptions' => 'total_perceptions',
                    'ips_amount' => 'total_ips',
                    'loan_amount' => 'total_loans',
                    'judicial_amount' => 'total_judicial',
                    'voluntary_amount' => 'total_voluntary',
                    'total_deductions' => 'total_deductions',
                    'net_salary' => 'total_net',
                ];

                if (isset($this->fieldToExcelCol['employee_name'])) {
                    $sheet->setCellValue($this->fieldToExcelCol['employee_name'].$row, 'TOTALES');
                }

                foreach ($monetaryFieldToTotal as $field => $totalKey) {
                    if (isset($this->fieldToExcelCol[$field])) {
                        $sheet->setCellValue(
                            $this->fieldToExcelCol[$field].$row,
                            (float) $this->totals[$totalKey]
                        );
                    }
                }

                $lastColLetter = $this->indexToColLetter($this->colCount - 1);
                $firstColLetter = 'A';

                $sheet->getStyle($firstColLetter.$row.':'.$lastColLetter.$row)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
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
     * Convierte un índice 0-based a letra de columna Excel (0=A, 25=Z, 26=AA, ...).
     */
    private function indexToColLetter(int $index): string
    {
        $letter = '';
        while ($index >= 0) {
            $letter = chr(65 + ($index % 26)).$letter;
            $index = intdiv($index, 26) - 1;
        }

        return $letter;
    }

    /**
     * Construye el mapa de campo => letra de columna Excel según las columnas seleccionadas.
     *
     * Preserva el orden de availableColumns() para que los headers y el map coincidan.
     */
    private function buildColMap(): void
    {
        $allCols = array_keys(static::availableColumns());
        $selectedOrdered = array_values(array_filter($allCols, fn ($k) => in_array($k, $this->columns)));

        $this->colCount = count($selectedOrdered);
        $this->fieldToExcelCol = [];
        foreach ($selectedOrdered as $idx => $field) {
            $this->fieldToExcelCol[$field] = $this->indexToColLetter($idx);
        }
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
            'total_base' => (float) $base->total_base,
            'total_perceptions' => (float) $base->total_perceptions,
            'total_ips' => (float) ($items['legal'] ?? 0),
            'total_loans' => (float) ($items['loan'] ?? 0),
            'total_judicial' => (float) ($items['judicial'] ?? 0),
            'total_voluntary' => (float) ($items['voluntary'] ?? 0),
            'total_deductions' => (float) $base->total_deductions,
            'total_net' => (float) $base->total_net,
        ];
    }
}
