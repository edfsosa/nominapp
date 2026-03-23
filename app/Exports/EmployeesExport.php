<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exporta el listado de empleados con sus datos personales y de contrato activo. */
class EmployeesExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * Query base con relaciones necesarias para el mapeo.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Employee::with([
            'branch.company',
            'activeContract.position.department',
        ])->orderBy('last_name')->orderBy('first_name');
    }

    /**
     * Encabezados de columna del archivo Excel.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Apellido',
            'Nombre',
            'CI',
            'Género',
            'Estado',
            'Empresa',
            'Sucursal',
            'Departamento',
            'Cargo',
            'Tipo de Empleo',
            'Salario',
            'Tipo de Nómina',
            'Método de Pago',
            'Teléfono',
            'Email',
            'Fecha de Nacimiento',
            'Inicio de Contrato',
        ];
    }

    /**
     * Mapea cada empleado a una fila del Excel.
     *
     * @param  Employee  $employee
     * @return array<int, mixed>
     */
    public function map($employee): array
    {
        $contract = $employee->activeContract;

        return [
            $employee->last_name,
            $employee->first_name,
            $employee->ci,
            $employee->gender_label ?? '—',
            Employee::getStatusOptions()[$employee->status] ?? $employee->status,
            $employee->branch?->company?->name ?? '—',
            $employee->branch?->name ?? '—',
            $contract?->position?->department?->name ?? '—',
            $contract?->position?->name ?? '—',
            $employee->employment_type_label ?? '—',
            $contract ? number_format((int) $contract->salary, 0, '', '.') : '—',
            $employee->payroll_type_label ?? '—',
            $employee->payment_method_label ?? '—',
            $employee->phone ?? '—',
            $employee->email ?? '—',
            $employee->birth_date?->format('d/m/Y') ?? '—',
            $contract?->start_date?->format('d/m/Y') ?? '—',
        ];
    }

    /**
     * Aplica negrita a la fila de encabezados.
     *
     * @param  Worksheet  $sheet
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
