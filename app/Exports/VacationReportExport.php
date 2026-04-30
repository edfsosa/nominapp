<?php

namespace App\Exports;

use App\Models\VacationBalance;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta el balance anual de vacaciones por empleado con los filtros activos del reporte.
 *
 * Cada fila representa un empleado con sus días con derecho, usados, pendientes y disponibles.
 */
class VacationReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  int  $year  Año del balance a exportar.
     * @param  int|null  $companyId  Filtrar por empresa (null = todas).
     * @param  int|null  $branchId  Filtrar por sucursal (null = todas).
     * @param  bool  $onlyUsed  Si true, solo incluye empleados con días usados > 0.
     */
    public function __construct(
        protected int $year,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected bool $onlyUsed = false,
    ) {}

    /**
     * Query base: balances del año con joins a empleados y sucursales.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return VacationBalance::query()
            ->select([
                'employee_vacation_balances.id',
                'employee_vacation_balances.years_of_service',
                'employee_vacation_balances.entitled_days',
                'employee_vacation_balances.used_days',
                'employee_vacation_balances.pending_days',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
            ])
            ->join('employees', 'employees.id', '=', 'employee_vacation_balances.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->where('employee_vacation_balances.year', $this->year)
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->onlyUsed, fn ($q) => $q->where('employee_vacation_balances.used_days', '>', 0))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name');
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
            'Antigüedad (años)',
            'Con Derecho (días)',
            'Usados (días)',
            'Pendientes (días)',
            'Disponibles (días)',
            'Progreso (%)',
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
        $available = max(0, $row->entitled_days - $row->used_days - $row->pending_days);
        $progress = $row->entitled_days > 0
            ? round(($row->used_days / $row->entitled_days) * 100, 1)
            : 0;

        return [
            $row->last_name.', '.$row->first_name,
            $row->ci,
            $row->branch_name,
            $row->years_of_service,
            $row->entitled_days,
            $row->used_days,
            $row->pending_days,
            $available,
            $progress,
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
