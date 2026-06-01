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
 * Un renglón por MerchandiseWithdrawal. Soporta selección de columnas
 * mediante availableColumns() / defaultColumns().
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
     * @param  array<string>  $columns  Claves de columnas a incluir (ver availableColumns()).
     */
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $status = null,
        protected ?int $employeeId = null,
        protected array $columns = [],
    ) {
        if (empty($this->columns)) {
            $this->columns = static::defaultColumns();
        }
    }

    public function title(): string
    {
        return 'Retiros';
    }

    /**
     * Todas las columnas disponibles para la hoja "Retiros": clave => label.
     *
     * @return array<string, string>
     */
    public static function availableColumns(): array
    {
        return [
            'employee_name' => 'Empleado',
            'ci' => 'CI',
            'branch_name' => 'Sucursal',
            'company_name' => 'Empresa',
            'total_amount' => 'Total (Gs.)',
            'installments_count' => 'Cant. Cuotas',
            'installment_amount' => 'Monto cuota (Gs.)',
            'outstanding_balance' => 'Saldo pendiente (Gs.)',
            'status' => 'Estado',
            'approved_at' => 'Aprobado el',
            'approved_by_name' => 'Aprobado por',
            'notes' => 'Notas',
        ];
    }

    /**
     * Columnas seleccionadas por defecto (todas excepto Empresa).
     *
     * @return array<string>
     */
    public static function defaultColumns(): array
    {
        return [
            'employee_name', 'ci', 'branch_name',
            'total_amount', 'installments_count', 'installment_amount',
            'outstanding_balance', 'status', 'approved_at', 'approved_by_name', 'notes',
        ];
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
            'company_name' => $row->company_name,
            'total_amount' => (float) $row->total_amount,
            'installments_count' => $row->installments_count,
            'installment_amount' => (float) $row->installment_amount,
            'outstanding_balance' => (float) $row->outstanding_balance,
            'status' => MerchandiseWithdrawal::getStatusLabel($row->status),
            'approved_at' => $row->approved_at?->format('d/m/Y') ?? '',
            'approved_by_name' => $row->approved_by_name ?? '',
            'notes' => $row->notes ?? '',
        ];

        return array_values(array_intersect_key($all, array_flip($this->columns)));
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
