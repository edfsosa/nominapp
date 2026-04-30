<?php

namespace App\Filament\Pages;

use App\Exports\VacationReportExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\VacationBalance;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reporte de vacaciones por empleado: muestra balance anual de días
 * con derecho, usados, pendientes y disponibles, filtrable por año,
 * empresa y sucursal.
 */
class VacationReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-sun';

    protected static ?string $navigationLabel = 'Reporte de Vacaciones';

    protected static ?string $navigationGroup = 'Empleados';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.vacation-report';

    protected ?string $heading = 'Reporte de Vacaciones';

    /**
     * Retorna el subheading dinámico con el año activo del filtro.
     */
    public function getSubheading(): ?string
    {
        $year = $this->tableFilters['year']['value'] ?? now()->year;

        return "Balance anual de vacaciones · Año {$year}";
    }

    /**
     * Acción de exportación a Excel con los filtros activos.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar reporte de vacaciones')
                ->modalDescription('Se exportará una fila por empleado con el balance del año y filtros seleccionados.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    [$year, $companyId, $branchId, $onlyUsed] = $this->resolveActiveFilters();
                    Notification::make()->success()->title('Exportación iniciada')->body('El archivo se descargará en breve.')->send();

                    return Excel::download(
                        new VacationReportExport($year, $companyId, $branchId, $onlyUsed),
                        'vacaciones_'.$year.'_'.now()->format('Y_m_d_H_i').'.xlsx'
                    );
                }),
        ];
    }

    /**
     * Define la tabla del reporte con filtros y columnas.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildQuery())
            ->filters($this->buildFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->persistFiltersInSession()
            ->defaultSort('last_name', 'asc')
            ->paginated([25, 50, 100])
            ->striped()
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->getStateUsing(fn ($record) => $record->last_name.', '.$record->first_name)
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('last_name', $direction)
                        ->orderBy('first_name', $direction))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn ($q) => $q->where('employees.first_name', 'like', "%{$search}%")
                            ->orWhere('employees.last_name', 'like', "%{$search}%")
                    ))
                    ->weight('medium'),

                TextColumn::make('ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('branch_name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-storefront')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('years_of_service')
                    ->label('Antigüedad')
                    ->getStateUsing(fn ($record) => $record->years_of_service.' '.($record->years_of_service === 1 ? 'año' : 'años'))
                    ->badge()
                    ->color('primary')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('years_of_service', $direction)),

                TextColumn::make('entitled_days')
                    ->label('Con derecho')
                    ->suffix(' días')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('used_days')
                    ->label('Usados')
                    ->suffix(' días')
                    ->badge()
                    ->color(fn ($record) => $record->used_days > 0 ? 'warning' : 'gray')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('pending_days')
                    ->label('Pendientes')
                    ->suffix(' días')
                    ->badge()
                    ->color(fn ($record) => $record->pending_days > 0 ? 'info' : 'gray')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('available_days')
                    ->label('Disponibles')
                    ->getStateUsing(fn ($record) => max(0, $record->entitled_days - $record->used_days - $record->pending_days))
                    ->suffix(' días')
                    ->badge()
                    ->color(fn ($record) => max(0, $record->entitled_days - $record->used_days - $record->pending_days) > 0 ? 'success' : 'danger')
                    ->alignCenter(),

                TextColumn::make('progress')
                    ->label('Progreso')
                    ->getStateUsing(function ($record) {
                        if ($record->entitled_days === 0) {
                            return '0%';
                        }

                        return round(($record->used_days / $record->entitled_days) * 100).'%';
                    })
                    ->badge()
                    ->color(function ($record) {
                        if ($record->entitled_days === 0) {
                            return 'gray';
                        }

                        $pct = ($record->used_days / $record->entitled_days) * 100;

                        if ($pct >= 100) {
                            return 'danger';
                        }

                        if ($pct >= 50) {
                            return 'warning';
                        }

                        return $pct > 0 ? 'success' : 'gray';
                    })
                    ->alignCenter(),
            ])
            ->emptyStateHeading('Sin datos para el período')
            ->emptyStateDescription('No hay balances de vacaciones para el año y filtros seleccionados. Generá los balances desde la sección de Vacaciones.')
            ->emptyStateIcon('heroicon-o-sun');
    }

    /**
     * Query base: VacationBalance con joins a employees y branches.
     */
    private function buildQuery(): Builder
    {
        return VacationBalance::query()
            ->select([
                'employee_vacation_balances.id',
                'employee_vacation_balances.employee_id',
                'employee_vacation_balances.year',
                'employee_vacation_balances.years_of_service',
                'employee_vacation_balances.entitled_days',
                'employee_vacation_balances.used_days',
                'employee_vacation_balances.pending_days',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
            ])
            ->join('employees', 'employees.id', '=', 'employee_vacation_balances.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id');
    }

    /**
     * Filtros del reporte: año, empresa, sucursal y opción de solo con vacaciones usadas.
     */
    private function buildFilters(): array
    {
        $yearOptions = collect(range(now()->year - 2, now()->year + 1))
            ->mapWithKeys(fn ($y) => [$y => (string) $y])
            ->all();

        return [
            SelectFilter::make('year')
                ->label('Año')
                ->options($yearOptions)
                ->default((string) now()->year)
                ->query(fn (Builder $query, array $data) => filled($data['value'])
                    ? $query->where('employee_vacation_balances.year', $data['value'])
                    : $query->where('employee_vacation_balances.year', now()->year)
                ),

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

            Filter::make('only_used')
                ->label('Solo con vacaciones usadas')
                ->toggle()
                ->query(fn (Builder $query) => $query->where('employee_vacation_balances.used_days', '>', 0)),
        ];
    }

    /**
     * Extrae los valores activos de los filtros para pasarlos al export.
     *
     * @return array{int, int|null, int|null, bool}
     */
    private function resolveActiveFilters(): array
    {
        $f = $this->tableFilters ?? [];

        return [
            isset($f['year']['value']) ? (int) $f['year']['value'] : now()->year,
            isset($f['company_id']['value']) ? (int) $f['company_id']['value'] : null,
            isset($f['branch_id']['value']) ? (int) $f['branch_id']['value'] : null,
            (bool) ($f['only_used']['isActive'] ?? false),
        ];
    }
}
