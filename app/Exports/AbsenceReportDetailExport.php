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
class AbsenceReportDetailExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * @param  string|null $fromDate     Fecha de inicio del período (Y-m-d).
     * @param  string|null $toDate       Fecha de fin del período (Y-m-d).
     * @param  int|null    $companyId    Filtrar por empresa.
     * @param  int|null    $branchId     Filtrar por sucursal.
     * @param  int|null    $departmentId Filtrar por departamento (contrato activo).
     */
    public function __construct(
        protected ?string $fromDate     = null,
        protected ?string $toDate       = null,
        protected ?int    $companyId    = null,
        protected ?int    $branchId     = null,
        protected ?int    $departmentId = null,
    ) {}

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
            ->when($this->fromDate,     fn($q) => $q->where('attendance_days.date', '>=', $this->fromDate))
            ->when($this->toDate,       fn($q) => $q->where('attendance_days.date', '<=', $this->toDate))
            ->when($this->branchId,     fn($q) => $q->where('employees.branch_id', $this->branchId))
            ->when($this->companyId,    fn($q) => $q->whereExists(fn($sub) => $sub->selectRaw(1)
                ->from('branches')
                ->whereColumn('branches.id', 'employees.branch_id')
                ->where('branches.company_id', $this->companyId)
            ))
            ->when($this->departmentId, fn($q) => $q->whereExists(fn($sub) => $sub->selectRaw(1)
                ->from('contracts')
                ->whereColumn('contracts.employee_id', 'employees.id')
                ->where('contracts.status', 'active')
                ->where('contracts.department_id', $this->departmentId)
            ))
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
        return [
            'Empleado',
            'CI',
            'Sucursal',
            'Departamento',
            'Cargo',
            'Fecha de Ausencia',
            'Motivo',
            'Estado',
            'Reportado por',
            'Fecha Reporte',
            'Revisado por',
            'Fecha Revisión',
            'Notas de Revisión',
            'Deducción (Gs.)',
        ];
    }

    /**
     * Mapea cada ausencia a una fila del Excel.
     *
     * @param  Absence $absence
     * @return array<int, mixed>
     */
    public function map($absence): array
    {
        $employee   = $absence->employee;
        $contract   = $employee?->activeContract;
        $position   = $contract?->position;
        $department = $position?->department;

        return [
            ($employee?->last_name . ', ' . $employee?->first_name) ?? '—',
            $employee?->ci ?? '—',
            $employee?->branch?->name ?? '—',
            $department?->name ?? '—',
            $position?->name ?? '—',
            $absence->attendanceDay?->date?->format('d/m/Y') ?? '—',
            $absence->reason ?? '—',
            Absence::getStatusLabel($absence->status),
            $absence->reportedBy?->name ?? 'Sistema',
            $absence->reported_at?->format('d/m/Y H:i') ?? '—',
            $absence->reviewedBy?->name ?? '—',
            $absence->reviewed_at?->format('d/m/Y H:i') ?? '—',
            $absence->review_notes ?? '—',
            $absence->employeeDeduction?->custom_amount ?? 0,
        ];
    }

    /**
     * Aplica estilos al encabezado de la hoja de cálculo.
     *
     * @param  Worksheet $sheet
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
