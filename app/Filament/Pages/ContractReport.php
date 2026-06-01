<?php

namespace App\Filament\Pages;

use App\Exports\ContractReportExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Radio;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reporte de contratos: vencimientos, períodos de prueba, sin contrato activo,
 * antigüedad, suspendidos, todos activos y rescindidos recientes.
 */
class ContractReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Reporte de Contratos';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.contract-report';

    protected ?string $heading = 'Reporte de Contratos';

    /**
     * Tab activo.
     *
     * @var string 'vencer'|'prueba'|'sin_contrato'|'antiguedad'|'suspendidos'|'activos'|'rescindidos'
     */
    public string $activeTab = 'vencer';

    /** Reconstruye la tabla (query, filtros, paginación) al cambiar de tab. */
    public function updatedActiveTab(): void
    {
        $this->resetTable();
    }

    /** Subheading dinámico según el tab activo. */
    public function getSubheading(): ?string
    {
        return match ($this->activeTab) {
            'vencer' => 'Contratos activos con fecha de vencimiento definida',
            'prueba' => 'Empleados actualmente en período de prueba',
            'sin_contrato' => 'Empleados sin ningún contrato activo',
            'antiguedad' => 'Contratos activos ordenados por fecha de inicio',
            'suspendidos' => 'Contratos con estado suspendido',
            'activos' => 'Todos los contratos activos',
            'rescindidos' => 'Contratos rescindidos o terminados',
            default => null,
        };
    }

    /**
     * Acciones del encabezado: exportar PDF y Excel con selector de columnas.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $columnOptions = ContractReportExport::availableColumns($this->activeTab);
        $columnDefaults = ContractReportExport::defaultColumns($this->activeTab);
        $orientationThreshold = 6;

        if (Company::active()->count() <= 1) {
            unset($columnOptions['company_name']);
            $columnDefaults = array_values(array_diff($columnDefaults, ['company_name']));
        }

        return [
            Action::make('export_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->modalHeading('Exportar reporte en PDF')
                ->modalSubmitActionLabel('Generar PDF')
                ->form([
                    CheckboxList::make('columns')
                        ->label('Columnas a incluir')
                        ->options($columnOptions)
                        ->default($columnDefaults)
                        ->columns(3)
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set) use ($orientationThreshold) {
                            $count = count($get('columns') ?? []);
                            $set('orientation', $count <= $orientationThreshold ? 'portrait' : 'landscape');
                        }),
                    Radio::make('orientation')
                        ->label('Orientación de la página')
                        ->helperText('Se ajusta automáticamente: Vertical para ≤ '.$orientationThreshold.' columnas, Horizontal para más.')
                        ->options(['portrait' => 'Vertical', 'landscape' => 'Horizontal'])
                        ->default(count($columnDefaults) > $orientationThreshold ? 'landscape' : 'portrait')
                        ->inline()
                        ->required(),
                ])
                ->action(function (array $data) {
                    [$companyId, $branchId, $days, $period] = $this->resolveActiveFilters();

                    $params = array_filter([
                        'tab' => $this->activeTab,
                        'companyId' => $companyId,
                        'branchId' => $branchId,
                        'days' => $days,
                        'period' => $period,
                        'columns' => implode(',', $data['columns']),
                        'orientation' => $data['orientation'] ?? 'landscape',
                    ], fn ($v) => $v !== null);

                    $url = route('contracts.report.pdf', $params);
                    $this->js("window.open('".addslashes($url)."', '_blank')");
                }),

            Action::make('export_excel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->modalHeading('Exportar reporte')
                ->modalDescription('Seleccione las columnas a incluir.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->form([
                    CheckboxList::make('columns')
                        ->label('Columnas a incluir')
                        ->options($columnOptions)
                        ->default($columnDefaults)
                        ->columns(3)
                        ->required(),
                ])
                ->action(function (array $data) {
                    [$companyId, $branchId, $days, $period] = $this->resolveActiveFilters();

                    Notification::make()->success()->title('Exportación iniciada')->body('El archivo se descargará en breve.')->send();

                    return Excel::download(
                        new ContractReportExport($this->activeTab, $companyId, $branchId, $days, $period, $data['columns']),
                        'contratos_'.$this->activeTab.'_'.now()->format('Y_m_d_H_i').'.xlsx'
                    );
                }),
        ];
    }

    /**
     * Define la tabla con columnas y filtros dinámicos según el tab activo.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildQuery())
            ->filters($this->buildFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->persistFiltersInSession()
            ->paginationPageOptions([25, 50, 100])
            ->striped()
            ->columns($this->buildColumns())
            ->emptyStateHeading('Sin registros para los filtros seleccionados')
            ->emptyStateIcon('heroicon-o-document-check');
    }

    // =========================================================================
    // QUERIES
    // =========================================================================

    /**
     * Retorna la query correspondiente al tab activo.
     */
    private function buildQuery(): Builder
    {
        return match ($this->activeTab) {
            'vencer' => $this->queryVencer(),
            'prueba' => $this->queryPrueba(),
            'sin_contrato' => $this->querySinContrato(),
            'antiguedad' => $this->queryAntiguedad(),
            'suspendidos' => $this->querySuspendidos(),
            'activos' => $this->queryActivos(),
            'rescindidos' => $this->queryRescindidos(),
            default => $this->queryVencer(),
        };
    }

    /**
     * Base de contratos con joins comunes para empleado, sucursal, empresa y cargo.
     *
     * @param  string  $status  Estado del contrato a filtrar.
     */
    private function baseContractQuery(string $status = 'active'): Builder
    {
        return Contract::query()
            ->select([
                'contracts.id',
                'contracts.start_date',
                'contracts.end_date',
                'contracts.trial_days',
                'contracts.status',
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

    /**
     * Tab "Por Vencer": contratos activos con end_date, ordenados por días restantes ASC.
     */
    private function queryVencer(): Builder
    {
        return $this->baseContractQuery()
            ->addSelect([
                DB::raw('DATEDIFF(contracts.end_date, CURDATE()) as days_until_expiry'),
            ])
            ->whereNotNull('contracts.end_date')
            ->whereRaw('DATEDIFF(contracts.end_date, CURDATE()) >= 0')
            ->orderByRaw('DATEDIFF(contracts.end_date, CURDATE()) ASC');
    }

    /**
     * Tab "Período de Prueba": contratos activos aún dentro del período de prueba.
     */
    private function queryPrueba(): Builder
    {
        return $this->baseContractQuery()
            ->addSelect([
                DB::raw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) as days_until_trial_end'),
                DB::raw('DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY) as trial_end_date'),
            ])
            ->where('contracts.trial_days', '>', 0)
            ->whereRaw('DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY) >= CURDATE()')
            ->orderByRaw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) ASC');
    }

    /**
     * Tab "Sin Contrato": empleados que no tienen ningún contrato activo.
     * La query base es Employee (no Contract).
     */
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
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name');
    }

    /**
     * Tab "Por Antigüedad": todos los contratos activos, ordenados por start_date ASC.
     * Incluye años y meses de servicio calculados.
     */
    private function queryAntiguedad(): Builder
    {
        return $this->baseContractQuery()
            ->addSelect([
                DB::raw('TIMESTAMPDIFF(YEAR, contracts.start_date, CURDATE()) as years_of_service'),
                DB::raw('TIMESTAMPDIFF(MONTH, contracts.start_date, CURDATE()) as months_of_service'),
            ])
            ->orderBy('contracts.start_date', 'asc');
    }

    /**
     * Tab "Suspendidos": contratos con status = suspended.
     */
    private function querySuspendidos(): Builder
    {
        return $this->baseContractQuery('suspended')
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name');
    }

    /**
     * Tab "Todos Activos": todos los contratos con status = active, ordenados por apellido.
     */
    private function queryActivos(): Builder
    {
        return $this->baseContractQuery()
            ->orderBy('employees.last_name')
            ->orderBy('employees.first_name');
    }

    /**
     * Tab "Rescindidos": contratos terminados, ordenados por fecha de rescisión DESC.
     * updated_at se usa como proxy de la fecha de rescisión.
     */
    private function queryRescindidos(): Builder
    {
        return Contract::query()
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
            ->orderByRaw('contracts.terminated_at DESC, contracts.updated_at DESC');
    }

    // =========================================================================
    // COLUMNS
    // =========================================================================

    /**
     * Construye el array de columnas de la tabla según el tab activo.
     *
     * @return array<int, TextColumn>
     */
    private function buildColumns(): array
    {
        return [
            // ── Siempre visibles ─────────────────────────────────────────────
            TextColumn::make('employee_name')
                ->label('Empleado')
                ->getStateUsing(fn ($record) => strtoupper($record->last_name).', '.$record->first_name)
                ->sortable(query: fn (Builder $query, string $direction) => $query
                    ->orderBy('employees.last_name', $direction)
                    ->orderBy('employees.first_name', $direction)
                )
                ->searchable(query: fn (Builder $query, string $search) => $query->where(
                    fn ($q) => $q
                        ->where('employees.first_name', 'like', "%{$search}%")
                        ->orWhere('employees.last_name', 'like', "%{$search}%")
                ))
                ->weight('medium'),

            TextColumn::make('ci')
                ->label('CI')
                ->badge()
                ->color('gray')
                ->searchable(query: fn (Builder $query, string $search) => $query->where('employees.ci', 'like', "%{$search}%"))
                ->copyable(),

            TextColumn::make('branch_name')
                ->label('Sucursal')
                ->badge()
                ->color('info')
                ->icon('heroicon-o-building-storefront'),

            // ── Solo tabs con contrato ────────────────────────────────────────
            TextColumn::make('position_name')
                ->label('Cargo')
                ->icon('heroicon-o-briefcase')
                ->placeholder('—')
                ->hidden(fn () => $this->activeTab === 'sin_contrato'),

            TextColumn::make('type')
                ->label('Tipo')
                ->formatStateUsing(fn ($state) => Contract::getTypeLabel($state))
                ->badge()
                ->color(fn ($state) => Contract::getTypeColor($state))
                ->hidden(fn () => $this->activeTab === 'sin_contrato'),

            // ── Salario (tabs donde es relevante) ────────────────────────────
            TextColumn::make('salary_type')
                ->label('Tipo Sal.')
                ->formatStateUsing(fn ($state) => match ($state) {
                    'mensual' => 'Mensual',
                    'jornal' => 'Jornal',
                    default => $state ?? '—',
                })
                ->badge()
                ->color(fn ($state) => $state === 'mensual' ? 'success' : 'warning')
                ->hidden(fn () => ! in_array($this->activeTab, ['antiguedad', 'suspendidos', 'activos', 'rescindidos'])),

            TextColumn::make('salary')
                ->label('Salario')
                ->getStateUsing(fn ($record) => $record->salary !== null
                    ? 'Gs. '.number_format((float) $record->salary, 0, ',', '.')
                    : '—'
                )
                ->hidden(fn () => ! in_array($this->activeTab, ['antiguedad', 'suspendidos', 'activos', 'rescindidos'])),

            // ── Fechas de contrato ────────────────────────────────────────────
            TextColumn::make('start_date')
                ->label('Inicio')
                ->date('d/m/Y')
                ->sortable()
                ->hidden(fn () => $this->activeTab === 'sin_contrato'),

            TextColumn::make('end_date')
                ->label('Vencimiento')
                ->date('d/m/Y')
                ->sortable()
                ->placeholder('Indefinido')
                ->hidden(fn () => in_array($this->activeTab, ['prueba', 'sin_contrato', 'antiguedad', 'suspendidos'])),

            TextColumn::make('terminated_at')
                ->label('Rescindido el')
                ->date('d/m/Y')
                ->sortable()
                ->hidden(fn () => $this->activeTab !== 'rescindidos'),

            // ── Prueba ────────────────────────────────────────────────────────
            TextColumn::make('trial_end_formatted')
                ->label('Fin de Prueba')
                ->getStateUsing(fn ($record) => $record->trial_end_date
                    ? Carbon::parse($record->trial_end_date)->format('d/m/Y')
                    : '—'
                )
                ->hidden(fn () => $this->activeTab !== 'prueba'),

            TextColumn::make('trial_days')
                ->label('Días de Prueba')
                ->suffix(' días')
                ->badge()
                ->color('gray')
                ->hidden(fn () => $this->activeTab !== 'prueba'),

            // ── Días restantes (vencer / prueba) ─────────────────────────────
            TextColumn::make('dias_restantes')
                ->label('Días Restantes')
                ->getStateUsing(fn ($record) => $this->activeTab === 'prueba'
                    ? $record->days_until_trial_end
                    : $record->days_until_expiry
                )
                ->badge()
                ->color(function ($record) {
                    $days = $this->activeTab === 'prueba'
                        ? $record->days_until_trial_end
                        : $record->days_until_expiry;
                    if ($days === null) {
                        return 'gray';
                    }
                    if ($days <= 15) {
                        return 'danger';
                    }
                    if ($days <= 30) {
                        return 'warning';
                    }

                    return 'success';
                })
                ->suffix(' días')
                ->hidden(fn () => ! in_array($this->activeTab, ['vencer', 'prueba'])),

            // ── Antigüedad ────────────────────────────────────────────────────
            TextColumn::make('years_of_service')
                ->label('Antigüedad')
                ->getStateUsing(function ($record) {
                    $years = (int) ($record->years_of_service ?? 0);
                    $months = (int) ($record->months_of_service ?? 0);
                    if ($years >= 1) {
                        $rem = $months - ($years * 12);

                        return $years.' año'.($years !== 1 ? 's' : '').($rem > 0 ? ', '.$rem.' mes'.($rem !== 1 ? 'es' : '') : '');
                    }

                    return $months.' mes'.($months !== 1 ? 'es' : '');
                })
                ->badge()
                ->color(function ($record) {
                    $years = (int) ($record->years_of_service ?? 0);
                    if ($years >= 10) {
                        return 'success';
                    }
                    if ($years >= 5) {
                        return 'info';
                    }
                    if ($years >= 1) {
                        return 'warning';
                    }

                    return 'gray';
                })
                ->hidden(fn () => $this->activeTab !== 'antiguedad'),

            // ── Estado empleado (sin contrato) ────────────────────────────────
            TextColumn::make('employee_status')
                ->label('Estado')
                ->formatStateUsing(fn ($state) => match ($state) {
                    'active' => 'Activo',
                    'inactive' => 'Inactivo',
                    'draft' => 'Borrador',
                    'suspended' => 'Suspendido',
                    default => $state ?? '—',
                })
                ->badge()
                ->color(fn ($state) => match ($state) {
                    'active' => 'success',
                    'inactive' => 'gray',
                    'suspended' => 'warning',
                    default => 'gray',
                })
                ->hidden(fn () => $this->activeTab !== 'sin_contrato'),
        ];
    }

    // =========================================================================
    // FILTERS
    // =========================================================================

    /**
     * Construye los filtros dinámicos según el tab activo.
     *
     * @return array<int, mixed>
     */
    private function buildFilters(): array
    {
        $filters = [];

        if (Company::active()->count() > 1) {
            $filters[] = SelectFilter::make('company_id')
                ->label('Empresa')
                ->options(fn () => Company::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->query(fn (Builder $query, array $data) => $data['value']
                    ? $query->where('branches.company_id', $data['value'])
                    : $query
                );
        }

        $filters[] = SelectFilter::make('branch_id')
            ->label('Sucursal')
            ->options(fn () => Branch::orderBy('name')->pluck('name', 'id'))
            ->searchable()
            ->query(fn (Builder $query, array $data) => $data['value']
                ? $query->where('employees.branch_id', $data['value'])
                : $query
            );

        if (in_array($this->activeTab, ['vencer', 'prueba'])) {
            $filters[] = SelectFilter::make('days')
                ->label('Vencer en')
                ->options(['30' => '30 días', '60' => '60 días', '90' => '90 días'])
                ->placeholder('Todos')
                ->query(function (Builder $query, array $data) {
                    if (! filled($data['value'])) {
                        return $query;
                    }
                    $days = (int) $data['value'];
                    if ($this->activeTab === 'prueba') {
                        return $query->whereRaw(
                            'DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) <= ?',
                            [$days]
                        );
                    }

                    return $query
                        ->whereRaw('DATEDIFF(contracts.end_date, CURDATE()) >= 0')
                        ->whereRaw('DATEDIFF(contracts.end_date, CURDATE()) <= ?', [$days]);
                });
        }

        if ($this->activeTab === 'rescindidos') {
            $filters[] = SelectFilter::make('period')
                ->label('Rescindidos en los últimos')
                ->options(['3' => '3 meses', '6' => '6 meses', '12' => '12 meses'])
                ->placeholder('Todos')
                ->query(function (Builder $query, array $data) {
                    if (! filled($data['value'])) {
                        return $query;
                    }

                    return $query->where('contracts.terminated_at', '>=', now()->subMonths((int) $data['value']));
                });
        }

        return $filters;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Extrae los filtros activos de la sesión para pasarlos a las exportaciones.
     *
     * @return array{int|null, int|null, int|null, int|null}
     */
    private function resolveActiveFilters(): array
    {
        $f = $this->tableFilters ?? [];

        return [
            isset($f['company_id']['value']) && $f['company_id']['value'] !== '' ? (int) $f['company_id']['value'] : null,
            isset($f['branch_id']['value']) && $f['branch_id']['value'] !== '' ? (int) $f['branch_id']['value'] : null,
            isset($f['days']['value']) && $f['days']['value'] !== '' ? (int) $f['days']['value'] : null,
            isset($f['period']['value']) && $f['period']['value'] !== '' ? (int) $f['period']['value'] : null,
        ];
    }
}
