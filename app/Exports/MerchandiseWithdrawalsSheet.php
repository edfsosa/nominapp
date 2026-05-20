<?php

namespace App\Exports;

use App\Models\MerchandiseWithdrawal;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hoja "Retiros" del reporte de mercaderías.
 *
 * Un renglón por MerchandiseWithdrawal con sus totales y estado.
 */
class MerchandiseWithdrawalsSheet implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * @param  string|null  $from  Fecha inicio (filtro sobre created_at).
     * @param  string|null  $to  Fecha fin (filtro sobre created_at).
     * @param  int|null  $companyId  Filtrar por empresa.
     * @param  int|null  $branchId  Filtrar por sucursal.
     * @param  string|null  $status  Filtrar por estado.
     * @param  int|null  $employeeId  Filtrar por empleado.
     */
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $status = null,
        protected ?int $employeeId = null,
    ) {}

    public function title(): string
    {
        return 'Retiros';
    }

    /**
     * Query base con todos los filtros aplicados.
     */
    public function query(): Builder
    {
        return MerchandiseWithdrawal::query()
            ->select([
                'merchandise_withdrawals.*',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.name as company_name',
                'approvers.name as approved_by_name',
            ])
            ->join('employees', 'employees.id', '=', 'merchandise_withdrawals.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->join('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('users as approvers', 'approvers.id', '=', 'merchandise_withdrawals.approved_by_id')
            ->when($this->from, fn ($q) => $q->whereDate('merchandise_withdrawals.created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('merchandise_withdrawals.created_at', '<=', $this->to))
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->status, fn ($q) => $q->where('merchandise_withdrawals.status', $this->status))
            ->when($this->employeeId, fn ($q) => $q->where('merchandise_withdrawals.employee_id', $this->employeeId))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('merchandise_withdrawals.created_at');
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
            'Empresa',
            'Total (Gs.)',
            'Cant. Cuotas',
            'Monto cuota (Gs.)',
            'Saldo pendiente (Gs.)',
            'Estado',
            'Aprobado el',
            'Aprobado por',
            'Notas',
        ];
    }

    /**
     * Mapea cada registro a una fila del Excel.
     *
     * @param  mixed  $row
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return [
            $row->last_name.', '.$row->first_name,
            $row->ci,
            $row->branch_name,
            $row->company_name,
            (float) $row->total_amount,
            $row->installments_count,
            (float) $row->installment_amount,
            (float) $row->outstanding_balance,
            MerchandiseWithdrawal::getStatusLabel($row->status),
            $row->approved_at?->format('d/m/Y') ?? '',
            $row->approved_by_name ?? '',
            $row->notes ?? '',
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
