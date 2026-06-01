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
 * Soporta selección de columnas mediante availableColumns() / defaultColumns().
 */
class VacationReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  int  $year  Año del período (filtra por start_date).
     * @param  int|null  $month  Mes opcional (1-12). Null = todos los meses del año.
     * @param  int|null  $companyId  Filtrar por empresa (null = todas).
     * @param  int|null  $branchId  Filtrar por sucursal (null = todas).
     * @param  string|null  $status  Filtrar por estado (null = todos).
     * @param  array<string>  $columns  Claves de columnas a incluir (ver availableColumns()).
     */
    public function __construct(
        protected int $year,
        protected ?int $month = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?string $status = null,
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
            'start_date' => 'Inicio',
            'end_date' => 'Fin',
            'return_date' => 'Reintegro',
            'business_days' => 'Días hábiles',
            'payment_amount' => 'Monto (Gs.)',
            'payment_method' => 'Forma de pago',
            'status' => 'Estado',
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
            'start_date', 'end_date', 'return_date',
            'business_days', 'payment_amount', 'payment_method', 'status',
        ];
    }

    /**
     * Query base: vacaciones con joins a employees, branches y companies.
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
                'vacations.return_date',
                'vacations.business_days',
                'vacations.payment_method',
                'vacations.status',
                'vacations.payment_amount',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.name as company_name',
            ])
            ->join('employees', 'employees.id', '=', 'vacations.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'branches.company_id')
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
            'start_date' => $row->start_date->format('d/m/Y'),
            'end_date' => $row->end_date->format('d/m/Y'),
            'return_date' => $row->return_date ? \Carbon\Carbon::parse($row->return_date)->format('d/m/Y') : '',
            'business_days' => $row->business_days,
            'payment_amount' => ($row->payment_amount !== null && $row->payment_amount > 0)
                ? (float) $row->payment_amount
                : null,
            'payment_method' => Vacation::getPaymentMethodLabel($row->payment_method ?? 'immediate'),
            'status' => Vacation::getStatusLabel($row->status),
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
