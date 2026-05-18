<?php

namespace App\Filament\Pages;

use App\Exports\ContractExpirationReportExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use Filament\Actions\Action;
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
 * Reporte de vencimiento de contratos y períodos de prueba activos.
 * Tabs: "Contratos por Vencer" y "Períodos de Prueba".
 */
class ContractExpirationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $navigationLabel = 'Vencimiento de Contratos';

    protected static ?string $navigationGroup = 'Empleados';

    protected static ?int $navigationSort = 100;

    protected static string $view = 'filament.pages.contract-expiration-report';

    protected ?string $heading = 'Vencimiento de Contratos y Períodos de Prueba';

    /** @var string Tab activo: 'contratos' | 'prueba'. */
    public string $activeTab = 'contratos';

    /**
     * Resetea la paginación al cambiar de tab.
     */
    public function updatedActiveTab(): void
    {
        $this->resetPage();
    }

    /**
     * Subheading dinámico según el tab activo.
     */
    public function getSubheading(): ?string
    {
        return $this->activeTab === 'prueba'
            ? 'Empleados actualmente en período de prueba'
            : 'Contratos activos con fecha de vencimiento definida';
    }

    /**
     * Acciones del encabezado: exportar PDF y Excel.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url(function () {
                    [$companyId, $branchId, $days] = $this->resolveActiveFilters();

                    return route('contracts.expiration.report.pdf', array_filter([
                        'tab' => $this->activeTab,
                        'companyId' => $companyId,
                        'branchId' => $branchId,
                        'days' => $days,
                    ], fn ($v) => $v !== null));
                })
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar reporte')
                ->modalDescription('Se exportará el tab activo con los filtros seleccionados.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    [$companyId, $branchId, $days] = $this->resolveActiveFilters();

                    Notification::make()->success()->title('Exportación iniciada')->body('El archivo se descargará en breve.')->send();

                    return Excel::download(
                        new ContractExpirationReportExport($this->activeTab, $companyId, $branchId, $days),
                        'vencimiento_contratos_'.$this->activeTab.'_'.now()->format('Y_m_d_H_i').'.xlsx'
                    );
                }),
        ];
    }

    /**
     * Define la tabla del reporte con columnas y filtros.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildQuery())
            ->filters($this->buildFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->paginated([25, 50, 100])
            ->striped()
            ->columns([
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
                    ->searchable()
                    ->copyable(),

                TextColumn::make('branch_name')
                    ->label('Sucursal')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-building-storefront'),

                TextColumn::make('position_name')
                    ->label('Cargo')
                    ->icon('heroicon-o-briefcase')
                    ->placeholder('—'),

                TextColumn::make('type')
                    ->label('Tipo de Contrato')
                    ->formatStateUsing(fn ($state) => Contract::getTypeLabel($state))
                    ->badge()
                    ->color(fn ($state) => Contract::getTypeColor($state)),

                TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->hidden(fn () => $this->activeTab === 'prueba'),

                TextColumn::make('trial_end_formatted')
                    ->label('Fin de Prueba')
                    ->getStateUsing(fn ($record) => $record->trial_end_date?->format('d/m/Y') ?? '—')
                    ->hidden(fn () => $this->activeTab !== 'prueba'),

                TextColumn::make('trial_days')
                    ->label('Días de Prueba')
                    ->suffix(' días')
                    ->badge()
                    ->color('gray')
                    ->hidden(fn () => $this->activeTab !== 'prueba'),

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
                    ->suffix(' días'),
            ])
            ->emptyStateHeading('Sin contratos para los filtros seleccionados')
            ->emptyStateIcon('heroicon-o-document-check');
    }

    /**
     * Query base: contratos activos con joins y columnas calculadas.
     * Sin filtros de empresa/sucursal/días — esos van en buildFilters().
     */
    private function buildQuery(): Builder
    {
        $query = Contract::query()
            ->select([
                'contracts.id',
                'contracts.start_date',
                'contracts.end_date',
                'contracts.trial_days',
                'contracts.status',
                'contracts.type',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
                'positions.name as position_name',
                DB::raw('DATEDIFF(contracts.end_date, CURDATE()) as days_until_expiry'),
                DB::raw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) as days_until_trial_end'),
            ])
            ->join('employees', 'employees.id', '=', 'contracts.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('positions', 'positions.id', '=', 'contracts.position_id')
            ->where('contracts.status', 'active');

        if ($this->activeTab === 'prueba') {
            $query
                ->where('contracts.trial_days', '>', 0)
                ->whereRaw('DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY) >= CURDATE()')
                ->orderByRaw('DATEDIFF(DATE_ADD(contracts.start_date, INTERVAL contracts.trial_days DAY), CURDATE()) ASC');
        } else {
            $query
                ->whereNotNull('contracts.end_date')
                ->orderByRaw('DATEDIFF(contracts.end_date, CURDATE()) ASC');
        }

        return $query;
    }

    /**
     * Filtros del reporte: empresa, sucursal y umbral de días.
     *
     * @return array<int, mixed>
     */
    private function buildFilters(): array
    {
        return [
            SelectFilter::make('company_id')
                ->label('Empresa')
                ->options(fn () => Company::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->query(fn (Builder $query, array $data) => $data['value']
                    ? $query->where('branches.company_id', $data['value'])
                    : $query
                ),

            SelectFilter::make('branch_id')
                ->label('Sucursal')
                ->options(fn () => Branch::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->query(fn (Builder $query, array $data) => $data['value']
                    ? $query->where('employees.branch_id', $data['value'])
                    : $query
                ),

            SelectFilter::make('days')
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
                }),
        ];
    }

    /**
     * Extrae los filtros activos para pasarlos a las exportaciones.
     *
     * @return array{int|null, int|null, int|null}
     */
    private function resolveActiveFilters(): array
    {
        $f = $this->tableFilters ?? [];

        return [
            isset($f['company_id']['value']) && $f['company_id']['value'] !== '' ? (int) $f['company_id']['value'] : null,
            isset($f['branch_id']['value']) && $f['branch_id']['value'] !== '' ? (int) $f['branch_id']['value'] : null,
            isset($f['days']['value']) && $f['days']['value'] !== '' ? (int) $f['days']['value'] : null,
        ];
    }
}
