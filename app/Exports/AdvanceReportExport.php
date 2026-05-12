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
     */
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $status = null,
        protected ?int $employeeId = null,
    ) {}

    /**
     * Query base: adelantos con joins a employees, branches y users.
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
                'advances.notes',
                'advances.created_at',
                'advances.approved_at',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'users.name as approved_by_name',
            ])
            ->join('employees', 'employees.id', '=', 'advances.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('users', 'users.id', '=', 'advances.approved_by_id')
            ->when($this->from, fn ($q) => $q->whereDate('advances.created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('advances.created_at', '<=', $this->to))
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->status, fn ($q) => $q->where('advances.status', $this->status))
            ->when($this->employeeId, fn ($q) => $q->where('advances.employee_id', $this->employeeId))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('advances.created_at');
    }

    /**
     * Encabezados de columna del archivo Excel.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Empleado',
            'CI',
            'Sucursal',
            'Monto (Gs.)',
            'Estado',
            'Solicitud',
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
            (float) $row->amount,
            Advance::getStatusLabel($row->status),
            $row->created_at->format('d/m/Y'),
            $row->approved_at?->format('d/m/Y') ?? '',
            $row->approved_by_name ?? '',
            $row->notes ?? '',
        ];
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
