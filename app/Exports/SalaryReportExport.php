<?php

namespace App\Exports;

use App\Models\Payroll;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta el reporte de salarios a Excel.
 *
 * Cada fila representa un recibo de nómina con desglose de deducciones por tipo.
 */
class SalaryReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
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
    ) {}

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
            'Préstamos/Adelantos (Gs.)',
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
}
