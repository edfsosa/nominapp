<?php

namespace App\Exports;

use App\Models\Advance;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exporta adelantos de salario a Excel, con filtro opcional por estado. */
class AdvancesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  array<int, string>|null  $status  Filtro de estados (null = todos).
     */
    public function __construct(
        protected ?array $status = null,
    ) {}

    /**
     * Consulta base de adelantos con eager-load de relaciones necesarias.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query()
    {
        return Advance::with(['employee', 'approvedBy'])
            ->when($this->status, fn ($q) => $q->whereIn('status', $this->status))
            ->orderBy('created_at', 'desc');
    }

    /**
     * Encabezados de columna del archivo.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        return [
            'Empleado',
            'CI',
            'Monto (Gs.)',
            'Estado',
            'Notas',
            'Aprobado el',
            'Aprobado por',
            'Creado',
            'Editado',
        ];
    }

    /**
     * Transforma un adelanto en fila de datos para el Excel.
     *
     * @param  Advance  $advance
     * @return array<int, mixed>
     */
    public function map($advance): array
    {
        return [
            $advance->employee->full_name,
            $advance->employee->ci,
            $advance->amount,
            Advance::getStatusLabel($advance->status),
            $advance->notes ?? '',
            $advance->approved_at?->format('d/m/Y H:i') ?? '',
            $advance->approvedBy?->name ?? '',
            $advance->created_at->format('d/m/Y H:i'),
            $advance->updated_at->format('d/m/Y H:i'),
        ];
    }

    /**
     * Aplica estilos al encabezado.
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
