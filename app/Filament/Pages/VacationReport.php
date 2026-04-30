<?php

namespace App\Filament\Pages;

use App\Exports\VacationReportExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Vacation;
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
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reporte de vacaciones individuales: muestra cada período de vacación tomado
 * por empleado, filtrable por año, mes, empresa, sucursal y estado.
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

    /** @var array<string, string> Nombres de meses en español. */
    private const MONTHS = [
        '1' => 'Enero',      '2' => 'Febrero',   '3' => 'Marzo',
        '4' => 'Abril',      '5' => 'Mayo',       '6' => 'Junio',
        '7' => 'Julio',      '8' => 'Agosto',     '9' => 'Septiembre',
        '10' => 'Octubre',    '11' => 'Noviembre',  '12' => 'Diciembre',
    ];

    /**
     * Retorna el subheading dinámico con el año y mes activos del filtro.
     */
    public function getSubheading(): ?string
    {
        $f = $this->tableFilters ?? [];
        $year = $f['year']['value'] ?? now()->year;
        $month = $f['month']['value'] ?? null;

        if ($month && isset(self::MONTHS[$month])) {
            return 'Períodos de vacación · '.self::MONTHS[$month].' '.$year;
        }

        return 'Períodos de vacación · Año '.$year;
    }

    /**
     * Acción de exportación a Excel con los filtros activos.
     *
     * @return array<int, Action>
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
                ->modalDescription('Se exportará una fila por período de vacación con los filtros seleccionados.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    [$year, $month, $companyId, $branchId, $status] = $this->resolveActiveFilters();
                    Notification::make()->success()->title('Exportación iniciada')->body('El archivo se descargará en breve.')->send();

                    $suffix = $month ? '_'.str_pad((string) $month, 2, '0', STR_PAD_LEFT) : '';

                    return Excel::download(
                        new VacationReportExport($year, $month, $companyId, $branchId, $status),
                        'vacaciones_'.$year.$suffix.'_'.now()->format('Y_m_d_H_i').'.xlsx'
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
            ->filtersFormColumns(5)
            ->persistFiltersInSession()
            ->defaultSort('employees.last_name', 'asc')
            ->paginated([25, 50, 100])
            ->striped()
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->getStateUsing(fn ($record) => $record->last_name.', '.$record->first_name)
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('employees.last_name', $direction)
                        ->orderBy('employees.first_name', $direction))
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

                TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('business_days')
                    ->label('Días hábiles')
                    ->suffix(' días')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => Vacation::getTypeLabel($state))
                    ->badge()
                    ->color(fn ($state) => Vacation::getTypeColor($state)),

                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn ($state) => Vacation::getStatusLabel($state))
                    ->badge()
                    ->color(fn ($state) => Vacation::getStatusColor($state))
                    ->icon(fn ($state) => Vacation::getStatusIcon($state)),
            ])
            ->emptyStateHeading('Sin vacaciones para el período')
            ->emptyStateDescription('No hay registros de vacaciones para los filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-sun');
    }

    /**
     * Query base: Vacation con joins a employees y branches.
     */
    private function buildQuery(): Builder
    {
        return Vacation::query()
            ->select([
                'vacations.id',
                'vacations.employee_id',
                'vacations.start_date',
                'vacations.end_date',
                'vacations.business_days',
                'vacations.type',
                'vacations.status',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
            ])
            ->join('employees', 'employees.id', '=', 'vacations.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id');
    }

    /**
     * Filtros del reporte: año, mes, empresa, sucursal y estado.
     *
     * @return array<int, mixed>
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
                    ? $query->whereYear('vacations.start_date', $data['value'])
                    : $query->whereYear('vacations.start_date', now()->year)
                ),

            SelectFilter::make('month')
                ->label('Mes')
                ->options(self::MONTHS)
                ->placeholder('Todos los meses')
                ->query(fn (Builder $query, array $data) => filled($data['value'])
                    ? $query->whereMonth('vacations.start_date', $data['value'])
                    : $query
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

            SelectFilter::make('status')
                ->label('Estado')
                ->options(Vacation::getStatusOptions())
                ->placeholder('Todos los estados')
                ->query(fn (Builder $query, array $data) => filled($data['value'])
                    ? $query->where('vacations.status', $data['value'])
                    : $query
                ),
        ];
    }

    /**
     * Extrae los valores activos de los filtros para pasarlos al export.
     *
     * @return array{int, int|null, int|null, int|null, string|null}
     */
    private function resolveActiveFilters(): array
    {
        $f = $this->tableFilters ?? [];

        return [
            isset($f['year']['value']) ? (int) $f['year']['value'] : now()->year,
            isset($f['month']['value']) && $f['month']['value'] !== '' ? (int) $f['month']['value'] : null,
            isset($f['company_id']['value']) && $f['company_id']['value'] !== '' ? (int) $f['company_id']['value'] : null,
            isset($f['branch_id']['value']) && $f['branch_id']['value'] !== '' ? (int) $f['branch_id']['value'] : null,
            isset($f['status']['value']) && $f['status']['value'] !== '' ? $f['status']['value'] : null,
        ];
    }
}
