<?php

namespace App\Filament\Pages;

use App\Exports\EmployeeReportExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
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
 * Reporte de empleados: nómina con fecha de ingreso, antigüedad, salario, cumpleaños y género.
 *
 * Los datos de contrato (salario, cargo, fecha de ingreso) provienen del contrato activo.
 */
class EmployeeReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'reporte-empleados';

    protected static ?string $title = 'Reporte de Empleados';

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Reporte de Empleados';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.employee-report';

    protected ?string $heading = 'Reporte de Empleados';

    /**
     * Retorna el subheading dinámico con los filtros activos.
     */
    public function getSubheading(): ?string
    {
        $f = $this->tableFilters ?? [];
        $gender = isset($f['gender']['value']) && $f['gender']['value'] !== '' ? $f['gender']['value'] : null;
        $status = isset($f['status']['value']) && $f['status']['value'] !== '' ? $f['status']['value'] : null;
        $birthMonth = isset($f['birth_month']['value']) && $f['birth_month']['value'] !== '' ? (int) $f['birth_month']['value'] : null;

        $parts = [];

        if ($gender) {
            $parts[] = Employee::getGenderOptions()[$gender] ?? $gender;
        }

        if ($status) {
            $parts[] = Employee::getStatusOptions()[$status] ?? $status;
        }

        if ($birthMonth) {
            $months = Employee::getMonthOptions();
            $parts[] = 'Cumpleaños en '.($months[$birthMonth] ?? $birthMonth);
        }

        return $parts ? implode(' · ', $parts) : 'Todos los empleados';
    }

    /**
     * Acciones de exportación (PDF y Excel) con los filtros activos.
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
                    [$companyId, $branchId, $gender, $birthMonth, $status] = $this->resolveActiveFilters();

                    return route('employees.report.pdf', array_filter([
                        'companyId' => $companyId,
                        'branchId' => $branchId,
                        'gender' => $gender,
                        'birthMonth' => $birthMonth,
                        'status' => $status,
                    ], fn ($v) => $v !== null));
                })
                ->openUrlInNewTab(),

            Action::make('export')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar reporte de empleados')
                ->modalDescription('Se exportará una fila por empleado con los filtros seleccionados.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    [$companyId, $branchId, $gender, $birthMonth, $status] = $this->resolveActiveFilters();

                    Notification::make()
                        ->success()
                        ->title('Exportación iniciada')
                        ->body('El archivo se descargará en breve.')
                        ->send();

                    return Excel::download(
                        new EmployeeReportExport($companyId, $branchId, $gender, $birthMonth, $status),
                        'empleados_'.now()->format('Y_m_d_H_i').'.xlsx'
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

                TextColumn::make('gender')
                    ->label('Género')
                    ->formatStateUsing(fn ($state) => Employee::getGenderOptions()[$state] ?? '—')
                    ->badge()
                    ->color(fn ($state) => Employee::getGenderColors()[$state] ?? 'gray')
                    ->icon(fn ($state) => Employee::getGenderIcons()[$state] ?? null),

                TextColumn::make('hire_date')
                    ->label('Fecha ingreso')
                    ->getStateUsing(fn ($record) => $record->hire_date)
                    ->date('d/m/Y')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('contracts.start_date', $direction)),

                TextColumn::make('years_of_service')
                    ->label('Antigüedad')
                    ->getStateUsing(function ($record) {
                        if ($record->hire_date === null) {
                            return '—';
                        }
                        $years = (int) $record->years_of_service;

                        return $years === 1 ? '1 año' : $years.' años';
                    })
                    ->badge()
                    ->color(fn ($record) => match (true) {
                        $record->years_of_service === null => 'gray',
                        $record->years_of_service >= 10 => 'success',
                        $record->years_of_service >= 5 => 'info',
                        $record->years_of_service >= 1 => 'warning',
                        default => 'gray',
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('contracts.start_date', $direction === 'asc' ? 'desc' : 'asc')),

                TextColumn::make('salary')
                    ->label('Salario')
                    ->getStateUsing(function ($record) {
                        if ($record->salary === null) {
                            return '—';
                        }
                        $label = $record->salary_type === 'jornal' ? '/día' : '/mes';

                        return 'Gs. '.number_format((float) $record->salary, 0, ',', '.').$label;
                    })
                    ->alignRight()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('contracts.salary', $direction)),

                TextColumn::make('position_name')
                    ->label('Cargo')
                    ->getStateUsing(fn ($record) => $record->position_name ?? '—')
                    ->sortable(),

                TextColumn::make('branch_name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-storefront')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->getStateUsing(fn ($record) => $record->status_label)
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

                TextColumn::make('birth_date')
                    ->label('Fecha nacimiento')
                    ->getStateUsing(function ($record) {
                        if (! $record->birth_date) {
                            return null;
                        }

                        return $record->birth_date->format('d/m/Y').' ('.$record->birth_date->age.' años)';
                    })
                    ->visible(fn ($record) => filled($record?->birth_date))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('birth_month_name')
                    ->label('Mes cumpleaños')
                    ->getStateUsing(function ($record) {
                        if (! $record->birth_date) {
                            return null;
                        }
                        $months = Employee::getMonthOptions();

                        return $months[$record->birth_date->month] ?? '—';
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->getStateUsing(fn ($record) => $record->phone ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading('Sin empleados para los filtros seleccionados')
            ->emptyStateDescription('Ajuste los filtros para ver resultados.')
            ->emptyStateIcon('heroicon-o-users');
    }

    /**
     * Query base: Employee con join a branches, companies, contrato activo y posición.
     */
    private function buildQuery(): Builder
    {
        return Employee::query()
            ->select([
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'employees.gender',
                'employees.birth_date',
                'employees.status',
                'employees.phone',
                'employees.branch_id',
                'branches.name as branch_name',
                'companies.id as company_id',
                'companies.name as company_name',
                'contracts.start_date as hire_date',
                'contracts.salary',
                'contracts.salary_type',
                'positions.name as position_name',
                DB::raw('TIMESTAMPDIFF(YEAR, contracts.start_date, CURDATE()) AS years_of_service'),
                DB::raw('MONTH(employees.birth_date) AS birth_month'),
            ])
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->join('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('contracts', function ($join) {
                $join->on('contracts.employee_id', '=', 'employees.id')
                    ->where('contracts.status', '=', 'active');
            })
            ->leftJoin('positions', 'positions.id', '=', 'contracts.position_id');
    }

    /**
     * Filtros del reporte: empresa, sucursal, género, mes de cumpleaños y estado.
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
                ->query(
                    fn (Builder $query, array $data) => $data['value']
                        ? $query->where('branches.company_id', $data['value'])
                        : $query
                );
        }

        return array_merge($filters, [
            SelectFilter::make('branch_id')
                ->label('Sucursal')
                ->options(fn () => Branch::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->query(
                    fn (Builder $query, array $data) => $data['value']
                        ? $query->where('employees.branch_id', $data['value'])
                        : $query
                ),

            SelectFilter::make('gender')
                ->label('Género')
                ->options(Employee::getGenderOptions())
                ->placeholder('Todos los géneros')
                ->query(
                    fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->where('employees.gender', $data['value'])
                        : $query
                )
                ->native(false),

            SelectFilter::make('birth_month')
                ->label('Mes de cumpleaños')
                ->options(Employee::getMonthOptions())
                ->placeholder('Todos los meses')
                ->query(
                    fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereRaw('MONTH(employees.birth_date) = ?', [$data['value']])
                        : $query
                )
                ->native(false),

            SelectFilter::make('status')
                ->label('Estado')
                ->options(Employee::getStatusOptions())
                ->placeholder('Todos los estados')
                ->query(
                    fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->where('employees.status', $data['value'])
                        : $query
                )
                ->native(false),
        ]);
    }

    /**
     * Extrae los valores activos de los filtros para pasarlos al export/PDF.
     *
     * @return array{int|null, int|null, string|null, int|null, string|null}
     */
    private function resolveActiveFilters(): array
    {
        $f = $this->tableFilters ?? [];

        return [
            isset($f['company_id']['value']) && $f['company_id']['value'] !== '' ? (int) $f['company_id']['value'] : null,
            isset($f['branch_id']['value']) && $f['branch_id']['value'] !== '' ? (int) $f['branch_id']['value'] : null,
            isset($f['gender']['value']) && $f['gender']['value'] !== '' ? $f['gender']['value'] : null,
            isset($f['birth_month']['value']) && $f['birth_month']['value'] !== '' ? (int) $f['birth_month']['value'] : null,
            isset($f['status']['value']) && $f['status']['value'] !== '' ? $f['status']['value'] : null,
        ];
    }
}
