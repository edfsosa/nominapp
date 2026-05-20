<?php

namespace App\Exports;

use App\Models\MerchandiseWithdrawalInstallment;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja "Cuotas" del reporte de mercaderías.
 *
 * Un renglón por MerchandiseWithdrawalInstallment con su estado y fecha de pago.
 */
class MerchandiseInstallmentsSheet implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  string|null  $from  Fecha inicio (filtro sobre withdrawal.created_at).
     * @param  string|null  $to  Fecha fin.
     * @param  int|null  $companyId  Filtrar por empresa.
     * @param  int|null  $branchId  Filtrar por sucursal.
     * @param  string|null  $withdrawalStatus  Filtrar por estado del retiro padre.
     * @param  int|null  $employeeId  Filtrar por empleado.
     */
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $withdrawalStatus = null,
        protected ?int $employeeId = null,
    ) {}

    public function title(): string
    {
        return 'Cuotas';
    }

    /**
     * Query de cuotas con joins al retiro padre, empleado, sucursal y empresa.
     */
    public function query(): Builder
    {
        return MerchandiseWithdrawalInstallment::query()
            ->select([
                'merchandise_withdrawal_installments.*',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'merchandise_withdrawals.installments_count as total_installments',
            ])
            ->join('merchandise_withdrawals', 'merchandise_withdrawals.id', '=', 'merchandise_withdrawal_installments.merchandise_withdrawal_id')
            ->join('employees', 'employees.id', '=', 'merchandise_withdrawals.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->join('companies', 'companies.id', '=', 'branches.company_id')
            ->when($this->from, fn ($q) => $q->whereDate('merchandise_withdrawals.created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('merchandise_withdrawals.created_at', '<=', $this->to))
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->withdrawalStatus, fn ($q) => $q->where('merchandise_withdrawals.status', $this->withdrawalStatus))
            ->when($this->employeeId, fn ($q) => $q->where('merchandise_withdrawals.employee_id', $this->employeeId))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('merchandise_withdrawals.id')
            ->orderBy('merchandise_withdrawal_installments.installment_number');
    }

    /**
     * Encabezados de columna.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Empleado',
            'CI',
            'Sucursal',
            'N° Cuota',
            'Monto (Gs.)',
            'Fecha vencimiento',
            'Estado',
            'Pagado el',
        ];
    }

    /**
     * Mapea cada cuota a una fila del Excel.
     *
     * @param  mixed  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        $statusLabels = [
            'pending' => 'Pendiente',
            'paid' => 'Pagada',
            'cancelled' => 'Cancelada',
        ];

        return [
            $row->last_name.', '.$row->first_name,
            $row->ci,
            $row->branch_name,
            $row->installment_number.'/'.$row->total_installments,
            (float) $row->amount,
            $row->due_date?->format('d/m/Y') ?? '',
            $statusLabels[$row->status] ?? $row->status,
            $row->paid_at?->format('d/m/Y') ?? '',
        ];
    }

    /**
     * Aplica estilos al encabezado de la hoja.
     *
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
