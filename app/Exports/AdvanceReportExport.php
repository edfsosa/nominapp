<?php

namespace App\Exports;

use App\Models\Advance;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta adelantos de salario con los filtros activos del reporte.
 *
 * Cada fila representa un adelanto individual.
 * Soporta selección de columnas mediante availableColumns() / defaultColumns().
 */
class AdvanceReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  string|null  $from  Fecha de inicio (filtro sobre created_at).
     * @param  string|null  $to  Fecha de fin (filtro sobre created_at).
     * @param  int|null  $companyId  Filtrar por empresa (null = todas).
     * @param  int|null  $branchId  Filtrar por sucursal (null = todas).
     * @param  string|null  $status  Filtrar por estado (null = todos).
     * @param  int|null  $employeeId  Filtrar por empleado (null = todos).
     * @param  string|null  $paymentMethod  Filtrar por método de pago (null = todos).
     * @param  array<string>  $columns  Claves de columnas a incluir (ver availableColumns()).
     */
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $status = null,
        protected ?int $employeeId = null,
        protected ?string $paymentMethod = null,
        protected array $columns = [],
    ) {
        if (empty($this->columns)) {
            $this->columns = static::defaultColumns();
        }
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
            'company_name' => 'Empresa',
            'amount' => 'Monto (Gs.)',
            'payment_method' => 'Método de pago',
            'status' => 'Estado',
            'created_at' => 'Solicitud',
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
            'amount', 'payment_method', 'status',
            'created_at', 'approved_at', 'approved_by_name', 'notes',
        ];
    }

    /**
     * Query base: adelantos con joins a employees, branches, companies y users.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Advance::query()
            ->select([
                'advances.id',
                'advances.amount',
                'advances.status',
                'advances.payment_method',
                'advances.notes',
                'advances.created_at',
                'advances.approved_at',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.name as company_name',
                'users.name as approved_by_name',
            ])
            ->join('employees', 'employees.id', '=', 'advances.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('users', 'users.id', '=', 'advances.approved_by_id')
            ->when($this->from, fn ($q) => $q->whereDate('advances.created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('advances.created_at', '<=', $this->to))
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->status, fn ($q) => $q->where('advances.status', $this->status))
            ->when($this->employeeId, fn ($q) => $q->where('advances.employee_id', $this->employeeId))
            ->when($this->paymentMethod, fn ($q) => $q->where('advances.payment_method', $this->paymentMethod))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('advances.created_at');
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
            'company_name' => $row->company_name ?? '',
            'amount' => (float) $row->amount,
            'payment_method' => Advance::getPaymentMethodLabel($row->payment_method),
            'status' => Advance::getStatusLabel($row->status),
            'created_at' => $row->created_at->format('d/m/Y'),
            'approved_at' => $row->approved_at?->format('d/m/Y') ?? '',
            'approved_by_name' => $row->approved_by_name ?? '',
            'notes' => $row->notes ?? '',
        ];

        return array_values(array_intersect_key($all, array_flip($this->columns)));
    }

    /**
     * Aplica estilos al encabezado de la hoja de cálculo.
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
