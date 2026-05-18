<?php

namespace App\Exports;

use App\Models\Advance;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Genera la plantilla Excel pre-poblada con empleados para importación de adelantos. */
class AdvancesTemplateExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  int|null  $companyId  Filtrar empleados por empresa.
     * @param  int|null  $branchId  Filtrar empleados por sucursal.
     */
    public function __construct(
        protected ?int $companyId = null,
        protected ?int $branchId = null,
    ) {}

    /**
     * Colección de empleados activos con salario definido según los filtros.
     */
    public function collection(): Collection
    {
        return Employee::query()
            ->where('status', 'active')
            ->whereHas('activeContract', fn ($c) => $c->whereNotNull('salary')->where('salary', '>', 0))
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->when(
                $this->companyId && ! $this->branchId,
                fn ($q) => $q->whereHas('branch', fn ($b) => $b->where('company_id', $this->companyId))
            )
            ->with(['activeContract', 'branch'])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->get();
    }

    /**
     * Encabezados de la plantilla.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['CI', 'Nombre', 'Sucursal', 'Monto (Gs.)', 'Método de pago', 'Notas'];
    }

    /**
     * Mapea un empleado a una fila de la plantilla.
     *
     * @param  Employee  $employee
     * @return array<int, mixed>
     */
    public function map($employee): array
    {
        $contractMethod = $employee->activeContract?->payment_method;

        return [
            $employee->ci,
            $employee->full_name,
            $employee->branch?->name ?? '',
            '',
            Advance::getPaymentMethodLabel($contractMethod === 'cash' ? 'cash' : 'transfer'),
            '',
        ];
    }

    /**
     * Aplica estilos: encabezado en negrita con fondo gris, columnas CI/Nombre/Sucursal bloqueadas visualmente.
     *
     * @return array<int|string, mixed>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'D9D9D9'],
                ],
            ],
            'A' => ['font' => ['color' => ['rgb' => '666666']]],
            'B' => ['font' => ['color' => ['rgb' => '666666']]],
            'C' => ['font' => ['color' => ['rgb' => '666666']]],
        ];
    }
}
