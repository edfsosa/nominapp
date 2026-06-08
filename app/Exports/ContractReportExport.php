<?php

namespace App\Exports;

use App\Models\Contract;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta el reporte de contratos a Excel.
 *
 * Soporta los 7 tabs del ContractReport: vencer, prueba, sin_contrato,
 * antiguedad, suspendidos, activos, rescindidos.
 * Las columnas disponibles y por defecto varían según el tab activo.
 */
class ContractReportExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  string  $tab  'vencer'|'prueba'|'sin_contrato'|'antiguedad'|'suspendidos'|'activos'|'rescindidos'
     * @param  int|null  $companyId  Filtro por empresa
     * @param  int|null  $branchId  Filtro por sucursal
     * @param  int|null  $days  Umbral de días al vencimiento (solo vencer/prueba)
     * @param  int|null  $period  Meses hacia atrás (solo rescindidos)
     * @param  array<string>  $columns  Claves de columnas seleccionadas
     * @param  string|null  $startDateFrom  Fecha de inicio del contrato desde (Y-m-d)
     * @param  string|null  $startDateUntil  Fecha de inicio del contrato hasta (Y-m-d)
     */
    public function __construct(
        private readonly string $tab,
        private readonly ?int $companyId,
        private readonly ?int $branchId,
        private readonly ?int $days,
        private readonly ?int $period,
        private readonly array $columns = [],
        private readonly ?string $startDateFrom = null,
        private readonly ?string $startDateUntil = null,
    ) {}

    /**
     * Columnas disponibles según el tab.
     *
     * @return array<string, string>
     */
    public static function availableColumns(string $tab = 'vencer'): array
    {
        $base = [
            'employee_name' => 'Empleado',
            'ci' => 'CI',
            'company_name' => 'Empresa',
            'branch_name' => 'Sucursal',
        ];

        return match ($tab) {
            'sin_contrato' => $base + [
                'employee_status' => 'Estado',
            ],
            'prueba' => $base + [
                'position_name' => 'Cargo',
                'contract_type' => 'Tipo de Contrato',
                'start_date' => 'Fecha de Inicio',
                'trial_days' => 'Días de Prueba',
                'trial_end_date' => 'Fin de Prueba',
                'days_remaining' => 'Días Restantes',
            ],
            'antiguedad' => $base + [
                'position_name' => 'Cargo',
                'contract_type' => 'Tipo de Contrato',
                'salary_type' => 'Tipo Salario',
                'salary' => 'Salario',
                'start_date' => 'Fecha de Inicio',
                'years_of_service' => 'Antigüedad',
            ],
            'rescindidos' => $base + [
                'position_name' => 'Cargo',
                'contract_type' => 'Tipo de Contrato',
                'salary_type' => 'Tipo Salario',
                'salary' => 'Salario',
                'start_date' => 'Fecha de Inicio',
                'terminated_at' => 'Rescindido el',
            ],
            'suspendidos', 'activos' => $base + [
                'position_name' => 'Cargo',
                'contract_type' => 'Tipo de Contrato',
                'salary_type' => 'Tipo Salario',
                'salary' => 'Salario',
                'start_date' => 'Fecha de Inicio',
                'end_date' => 'Vencimiento',
            ],
            default => $base + [   // 'vencer'
                'position_name' => 'Cargo',
                'contract_type' => 'Tipo de Contrato',
                'start_date' => 'Fecha de Inicio',
                'end_date' => 'Vencimiento',
                'days_remaining' => 'Días Restantes',
            ],
        };
    }

    /**
     * Columnas por defecto según el tab (todas disponibles excepto Empresa).
     *
     * @return array<string>
     */
    public static function defaultColumns(string $tab = 'vencer'): array
    {
        $all = static::availableColumns($tab);
        unset($all['company_name']);

        return array_keys($all);
    }

    /**
     * Query con los mismos filtros que la página, según el tab activo.
     */
    public function query(): Builder
    {
        $query = match ($this->tab) {
            'sin_contrato' => $this->querySinContrato(),
            'prueba' => $this->queryPrueba(),
            'antiguedad' => $this->queryAntiguedad(),
            'suspendidos' => $this->querySuspendidos(),
            'activos' => $this->queryActivos(),
            'rescindidos' => $this->queryRescindidos(),
            default => $this->queryVencer(),
        };

        // Filtros comunes: empresa y sucursal
        if ($this->companyId) {
            $query->where('branches.company_id', $this->companyId);
        }
        if ($this->branchId) {
            $query->where('employees.branch_id', $this->branchId);
        }

        // Filtro por período de inicio (tabs con contrato)
        if (in_array($this->tab, ['vencer', 'activos', 'antiguedad', 'rescindidos'])) {
            if ($this->startDateFrom) {
                $query->where('contracts.start_date', '>=', $this->startDateFrom);
            }
            if ($this->startDateUntil) {
                $query->where('contracts.start_date', '<=', $this->startDateUntil);
            }
        }

        return $query;
    }

    /**
     * Encabezados de columna filtrados según las columnas seleccionadas.
     *
     * @return array<int, string>
     */
    public function headings(): array
    {
        $all = static::availableColumns($this->tab);
        $selected = empty($this->columns) ? static::defaultColumns($this->tab) : $this->columns;

        return array_values(array_intersect_key($all, array_flip($selected)));
    }

    /**
     * Mapea cada registro a una fila del Excel con las columnas seleccionadas.
     *
     * @param  mixed  $record
     * @return array<int, mixed>
     */
    public function map($record): array
    {
        $selected = empty($this->columns) ? static::defaultColumns($this->tab) : $this->columns;

        $row = match ($this->tab) {
            'sin_contrato' => $this->mapSinContrato($record),
            'prueba' => $this->mapPrueba($record),
            'antiguedad' => $this->mapAntiguedad($record),
            'rescindidos' => $this->mapRescindidos($record),
            default => $this->mapDefault($record),
        };

        return array_values(array_intersect_key($row, array_flip($selected)));
    }

    /** Encabezado en negrita. */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    // =========================================================================
    // QUERIES INTERNAS
    // =========================================================================

    private function baseContractQuery(string $status = 'active'): Builder
    {
        return Contract::query()
            ->select([
                'contracts.id',
                'contracts.start_date',
                'contracts.end_date',
                'contracts.trial_days',
                'contracts.type',
                'contracts.salary_type',
                'contracts.salary',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
                'positions.name as position_name',
            ])
            ->join('employees', 'employees.id', '=', 'contracts.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('positions', 'positions.id', '=', 'contracts.position_id')
            ->where('contracts.status', $status);
    }

    private function queryVencer(): Builder
    {
        $q = $this->baseContractQuery()
            ->addSelect([DB::raw('DATEDIFF(contracts.end_date, CURDATE()) as days_until_expiry')])
            ->whereNotNull('contracts.end_date')
            ->whereRaw('DATEDIFF(contracts.end_date, CURDATE()) >= 0')
            ->orderBy('companies.name')->orderBy('branches.name')
            ->orderByRaw('DATEDIFF(contracts.end_date, CURDATE()) ASC');

        if ($this->days) {
            $q->whereRaw('DATEDIFF(contracts.end_date, CURDATE()) <= ?', [$this->days]);
        }

        return $q;
    }

    private function queryPrueba(): Builder
    {
        $q = $this->baseContractQuery()
            ->addSelect([
                DB::raw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) as days_until_trial_end'),
                DB::raw('DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY) as trial_end_date'),
            ])
            ->where('contracts.trial_days', '>', 0)
            ->whereRaw('DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY) >= CURDATE()')
            ->orderBy('companies.name')->orderBy('branches.name')
            ->orderByRaw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) ASC');

        if ($this->days) {
            $q->whereRaw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) <= ?', [$this->days]);
        }

        return $q;
    }

    private function querySinContrato(): Builder
    {
        return Employee::query()
            ->select([
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'employees.status as employee_status',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
            ])
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'branches.company_id')
            ->whereDoesntHave('contracts', fn ($q) => $q->where('status', 'active'))
            ->orderBy('companies.name')->orderBy('branches.name')
            ->orderBy('employees.last_name')->orderBy('employees.first_name');
    }

    private function queryAntiguedad(): Builder
    {
        return $this->baseContractQuery()
            ->addSelect([
                DB::raw('TIMESTAMPDIFF(YEAR, contracts.start_date, CURDATE()) as years_of_service'),
                DB::raw('TIMESTAMPDIFF(MONTH, contracts.start_date, CURDATE()) as months_of_service'),
            ])
            ->orderBy('companies.name')->orderBy('branches.name')
            ->orderBy('contracts.start_date', 'asc');
    }

    private function querySuspendidos(): Builder
    {
        return $this->baseContractQuery('suspended')
            ->orderBy('companies.name')->orderBy('branches.name')
            ->orderBy('employees.last_name')->orderBy('employees.first_name');
    }

    private function queryActivos(): Builder
    {
        return $this->baseContractQuery()
            ->orderBy('companies.name')->orderBy('branches.name')
            ->orderBy('employees.last_name')->orderBy('employees.first_name');
    }

    private function queryRescindidos(): Builder
    {
        $q = Contract::query()
            ->select([
                'contracts.id',
                'contracts.start_date',
                'contracts.end_date',
                'contracts.terminated_at',
                'contracts.type',
                'contracts.salary_type',
                'contracts.salary',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
                'positions.name as position_name',
            ])
            ->join('employees', 'employees.id', '=', 'contracts.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('positions', 'positions.id', '=', 'contracts.position_id')
            ->where('contracts.status', 'terminated')
            ->orderBy('companies.name')->orderBy('branches.name')
            ->orderByRaw('contracts.terminated_at DESC, contracts.updated_at DESC');

        if ($this->period) {
            $q->where('contracts.terminated_at', '>=', now()->subMonths($this->period));
        }

        return $q;
    }

    // =========================================================================
    // MAP HELPERS
    // =========================================================================

    private function mapBase(mixed $r): array
    {
        return [
            'employee_name' => strtoupper($r->last_name).', '.$r->first_name,
            'ci' => $r->ci,
            'company_name' => $r->company_name ?? '—',
            'branch_name' => $r->branch_name,
        ];
    }

    private function mapSinContrato(mixed $r): array
    {
        return $this->mapBase($r) + [
            'employee_status' => match ($r->employee_status) {
                'active' => 'Activo',
                'inactive' => 'Inactivo',
                'suspended' => 'Suspendido',
                'draft' => 'Borrador',
                default => $r->employee_status ?? '—',
            },
        ];
    }

    private function mapPrueba(mixed $r): array
    {
        return $this->mapBase($r) + [
            'position_name' => $r->position_name ?? '—',
            'contract_type' => Contract::getTypeLabel($r->type),
            'start_date' => Carbon::parse($r->start_date)->format('d/m/Y'),
            'trial_days' => $r->trial_days.' días',
            'trial_end_date' => $r->trial_end_date
                ? Carbon::parse($r->trial_end_date)->format('d/m/Y')
                : '—',
            'days_remaining' => $r->days_until_trial_end.' días',
        ];
    }

    private function mapAntiguedad(mixed $r): array
    {
        $years = (int) ($r->years_of_service ?? 0);
        $months = (int) ($r->months_of_service ?? 0);
        if ($years >= 1) {
            $rem = $months - ($years * 12);
            $label = $years.' año'.($years !== 1 ? 's' : '').($rem > 0 ? ', '.$rem.' mes'.($rem !== 1 ? 'es' : '') : '');
        } else {
            $label = $months.' mes'.($months !== 1 ? 'es' : '');
        }

        return $this->mapBase($r) + [
            'position_name' => $r->position_name ?? '—',
            'contract_type' => Contract::getTypeLabel($r->type),
            'salary_type' => $r->salary_type === 'mensual' ? 'Mensual' : 'Jornal',
            'salary' => $r->salary ? 'Gs. '.number_format((float) $r->salary, 0, ',', '.') : '—',
            'start_date' => Carbon::parse($r->start_date)->format('d/m/Y'),
            'years_of_service' => $label,
        ];
    }

    private function mapRescindidos(mixed $r): array
    {
        return $this->mapBase($r) + [
            'position_name' => $r->position_name ?? '—',
            'contract_type' => Contract::getTypeLabel($r->type),
            'salary_type' => $r->salary_type === 'mensual' ? 'Mensual' : 'Jornal',
            'salary' => $r->salary ? 'Gs. '.number_format((float) $r->salary, 0, ',', '.') : '—',
            'start_date' => $r->start_date ? Carbon::parse($r->start_date)->format('d/m/Y') : '—',
            'terminated_at' => $r->terminated_at ? Carbon::parse($r->terminated_at)->format('d/m/Y') : '—',
        ];
    }

    private function mapDefault(mixed $r): array
    {
        $base = $this->mapBase($r) + [
            'position_name' => $r->position_name ?? '—',
            'contract_type' => Contract::getTypeLabel($r->type),
            'salary_type' => $r->salary_type === 'mensual' ? 'Mensual' : 'Jornal',
            'salary' => $r->salary ? 'Gs. '.number_format((float) $r->salary, 0, ',', '.') : '—',
            'start_date' => $r->start_date ? Carbon::parse($r->start_date)->format('d/m/Y') : '—',
            'end_date' => $r->end_date ? Carbon::parse($r->end_date)->format('d/m/Y') : 'Indefinido',
        ];

        if (isset($r->days_until_expiry)) {
            $base['days_remaining'] = $r->days_until_expiry.' días';
        }

        return $base;
    }
}
