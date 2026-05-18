<?php

namespace App\Exports;

use App\Models\Contract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exporta el reporte de vencimiento de contratos o períodos de prueba a Excel. */
class ContractExpirationReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  string  $tab  'contratos' | 'prueba'
     * @param  int|null  $companyId  Filtro por empresa
     * @param  int|null  $branchId  Filtro por sucursal
     * @param  int|null  $days  Umbral de días al vencimiento
     */
    public function __construct(
        private readonly string $tab,
        private readonly ?int $companyId,
        private readonly ?int $branchId,
        private readonly ?int $days,
    ) {}

    /**
     * Query con los mismos joins y filtros que la página.
     */
    public function query(): Builder
    {
        $query = Contract::query()
            ->select([
                'contracts.id',
                'contracts.start_date',
                'contracts.end_date',
                'contracts.trial_days',
                'contracts.type',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.name as company_name',
                'positions.name as position_name',
                DB::raw('DATEDIFF(contracts.end_date, CURDATE()) as days_until_expiry'),
                DB::raw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) as days_until_trial_end'),
                DB::raw('DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY) as trial_end_date'),
            ])
            ->join('employees', 'employees.id', '=', 'contracts.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('positions', 'positions.id', '=', 'contracts.position_id')
            ->where('contracts.status', 'active')
            ->when($this->companyId, fn ($q) => $q->where('branches.company_id', $this->companyId))
            ->when($this->branchId, fn ($q) => $q->where('employees.branch_id', $this->branchId));

        if ($this->tab === 'prueba') {
            $query
                ->where('contracts.trial_days', '>', 0)
                ->whereRaw('DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY) >= CURDATE()')
                ->orderByRaw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) ASC');

            if ($this->days) {
                $query
                    ->whereRaw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) >= 0')
                    ->whereRaw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) <= ?', [$this->days]);
            }
        } else {
            $query
                ->whereNotNull('contracts.end_date')
                ->orderByRaw('DATEDIFF(contracts.end_date, CURDATE()) ASC');

            if ($this->days) {
                $query
                    ->whereRaw('DATEDIFF(contracts.end_date, CURDATE()) >= 0')
                    ->whereRaw('DATEDIFF(contracts.end_date, CURDATE()) <= ?', [$this->days]);
            }
        }

        return $query;
    }

    /**
     * Encabezados de columna según el tab.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        $base = ['Empleado', 'CI', 'Empresa', 'Sucursal', 'Cargo', 'Tipo de Contrato', 'Fecha Inicio'];

        if ($this->tab === 'prueba') {
            return [...$base, 'Días de Prueba', 'Fin de Prueba', 'Días Restantes'];
        }

        return [...$base, 'Fecha de Vencimiento', 'Días Restantes'];
    }

    /**
     * Mapea cada registro a una fila del Excel.
     *
     * @param  mixed  $record
     * @return array<int, mixed>
     */
    public function map($record): array
    {
        $base = [
            strtoupper($record->last_name).', '.$record->first_name,
            $record->ci,
            $record->company_name ?? '—',
            $record->branch_name,
            $record->position_name ?? '—',
            Contract::getTypeLabel($record->type),
            $record->start_date->format('d/m/Y'),
        ];

        if ($this->tab === 'prueba') {
            $trialEnd = $record->trial_end_date
                ? \Carbon\Carbon::parse($record->trial_end_date)->format('d/m/Y')
                : '—';

            return [...$base, $record->trial_days.' días', $trialEnd, $record->days_until_trial_end.' días'];
        }

        return [
            ...$base,
            $record->end_date->format('d/m/Y'),
            $record->days_until_expiry.' días',
        ];
    }

    /**
     * Encabezado en negrita.
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
