<?php

namespace App\Exports;

use App\Models\Vacation;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta períodos individuales de vacación con los filtros activos del reporte.
 *
 * Cada fila representa un período de vacación de un empleado.
 */
class VacationReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  int  $year  Año del período (filtra por start_date).
     * @param  int|null  $month  Mes opcional (1-12). Null = todos los meses del año.
     * @param  int|null  $companyId  Filtrar por empresa (null = todas).
     * @param  int|null  $branchId  Filtrar por sucursal (null = todas).
     * @param  string|null  $status  Filtrar por estado (null = todos).
     */
    public function __construct(
        protected int $year,
        protected ?int $month = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $status = null,
    ) {}

    /**
     * Query base: vacaciones con joins a employees y branches.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Vacation::query()
            ->select([
                'vacations.id',
                'vacations.start_date',
                'vacations.end_date',
                'vacations.business_days',
                'vacations.type',
                'vacations.status',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
            ])
            ->join('employees', 'employees.id', '=', 'vacations.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->whereYear('vacations.start_date', $this->year)
            ->when($this->month, fn ($q) => $q->whereMonth('vacations.start_date', $this->month))
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->status, fn ($q) => $q->where('vacations.status', $this->status))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('vacations.start_date');
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
            'Inicio',
            'Fin',
            'Días hábiles',
            'Tipo',
            'Estado',
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
            $row->start_date->format('d/m/Y'),
            $row->end_date->format('d/m/Y'),
            $row->business_days,
            Vacation::getTypeLabel($row->type),
            Vacation::getStatusLabel($row->status),
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
