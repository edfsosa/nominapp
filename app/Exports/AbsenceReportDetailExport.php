<?php

namespace App\Exports;

use App\Models\Absence;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta el detalle de ausencias para el período y filtros dados.
 *
 * Cada fila representa una ausencia individual con sus datos completos:
 * fecha, motivo, estado, revisión y monto de deducción generada.
 */
class AbsenceReportDetailExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  string|null  $fromDate  Fecha de inicio del período (Y-m-d).
     * @param  string|null  $toDate  Fecha de fin del período (Y-m-d).
     * @param  int|null  $companyId  Filtrar por empresa.
     * @param  int|null  $branchId  Filtrar por sucursal.
     * @param  int|null  $departmentId  Filtrar por departamento (contrato activo).
     * @param  int|null  $employeeId  Filtrar por empleado específico.
     */
    public function __construct(
        protected ?string $fromDate = null,
        protected ?string $toDate = null,
        protected ?int $companyId = null,
        protected ?int $branchId = null,
        protected ?int $departmentId = null,
        protected ?int $employeeId = null,
        protected array $columns = [],
    ) {
        if (empty($this->columns)) {
            $this->columns = static::defaultColumns();
        }
    }

    /** @return array<string, string> */
    public static function availableColumns(): array
    {
        return [
            'employee_name' => 'Empleado',
            'ci' => 'CI',
            'branch_name' => 'Sucursal',
            'department_name' => 'Departamento',
            'position_name' => 'Cargo',
            'date' => 'Fecha de Ausencia',
            'reason' => 'Motivo',
            'status' => 'Estado',
            'reported_by' => 'Reportado por',
            'report_date' => 'Fecha Reporte',
            'reviewed_by' => 'Revisado por',
            'review_date' => 'Fecha Revisión',
            'review_notes' => 'Notas de Revisión',
            'deduction_amount' => 'Deducción (Gs.)',
        ];
    }

    /** @return array<string> */
    public static function defaultColumns(): array
    {
        return array_keys(static::availableColumns());
    }

    /**
     * Query con una fila por ausencia, con relaciones eager-loaded.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Absence::query()
            ->with([
                'employee.branch',
                'employee.activeContract.position.department',
                'attendanceDay',
                'employeeDeduction',
                'reportedBy',
                'reviewedBy',
            ])
            ->join('employees', 'employees.id', '=', 'absences.employee_id')
            ->join('attendance_days', 'attendance_days.id', '=', 'absences.attendance_day_id')
            ->select('absences.*')
            ->when($this->fromDate, fn ($q) => $q->where('attendance_days.date', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->where('attendance_days.date', '<=', $this->toDate))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->companyId, fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw(1)
                ->from('branches')
                ->whereColumn('branches.id', 'employees.branch_id')
                ->where('branches.company_id', $this->companyId)
            ))
            ->when($this->departmentId, fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw(1)
                ->from('contracts')
                ->whereColumn('contracts.employee_id', 'employees.id')
                ->where('contracts.status', 'active')
                ->where('contracts.department_id', $this->departmentId)
            ))
            ->when($this->employeeId, fn ($q) => $q->where('employees.id', $this->employeeId))
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name')
            ->orderBy('attendance_days.date');
    }

    /**
     * Encabezados de columna del archivo Excel.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_values(array_intersect_key(static::availableColumns(), array_flip($this->columns)));
    }

    /**
     * Mapea cada ausencia a una fila del Excel.
     *
     * @param  Absence  $absence
     * @return array<int, mixed>
     */
    public function map($absence): array
    {
        $employee = $absence->employee;
        $contract = $employee?->activeContract;
        $position = $contract?->position;
        $department = $position?->department;

        $all = [
            'employee_name' => ($employee?->last_name.', '.$employee?->first_name) ?? '—',
            'ci' => $employee?->ci ?? '—',
            'branch_name' => $employee?->branch?->name ?? '—',
            'department_name' => $department?->name ?? '—',
            'position_name' => $position?->name ?? '—',
            'date' => $absence->attendanceDay?->date?->format('d/m/Y') ?? '—',
            'reason' => $absence->reason ?? '—',
            'status' => Absence::getStatusLabel($absence->status),
            'reported_by' => $absence->reportedBy?->name ?? 'Sistema',
            'report_date' => $absence->reported_at?->format('d/m/Y H:i') ?? '—',
            'reviewed_by' => $absence->reviewedBy?->name ?? '—',
            'review_date' => $absence->reviewed_at?->format('d/m/Y H:i') ?? '—',
            'review_notes' => $absence->review_notes ?? '—',
            'deduction_amount' => $absence->employeeDeduction?->custom_amount ?? 0,
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
