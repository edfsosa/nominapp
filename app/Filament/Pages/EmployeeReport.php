<?php

namespace App\Filament\Pages;

use App\Exports\EmployeeReportExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Department;
use App\Models\Employee;
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
 * Reporte de empleados: nómina con fecha de ingreso, antigüedad, salario, cumpleaños y género.
 *
 * Los datos de contrato (salario, cargo, departamento, fecha de ingreso) provienen del contrato activo.
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
     * Inicializa el filtro de estado en 'active' si no está explícitamente establecido en la sesión.
     *
     * El trait mountInteractsWithTable() se ejecuta antes de mount() en Livewire 3, por lo que
     * aquí $tableFilters ya está cargado desde sesión. Si la sesión guardó null para status
     * (sesión anterior al default o filtro sin selección), lo dejamos en 'active'.
     */
    public function mount(): void
    {
        $this->tableFilters['status']['value'] ??= 'active';
    }

    /**
     * Retorna el subheading dinámico con los filtros activos.
     */
    public function getSubheading(): ?string
    {
        $f = $this->tableFilters ?? [];

        $gender = isset($f['gender']['value']) && $f['gender']['value'] !== '' ? $f['gender']['value'] : null;
        $status = isset($f['status']['value']) && $f['status']['value'] !== '' ? $f['status']['value'] : null;
        $birthMonth = isset($f['birth_month']['value']) && $f['birth_month']['value'] !== '' ? (int) $f['birth_month']['value'] : null;
        $deptId = isset($f['department_id']['value']) && $f['department_id']['value'] !== '' ? (int) $f['department_id']['value'] : null;
        $contractType = isset($f['contract_type']['value']) && $f['contract_type']['value'] !== '' ? $f['contract_type']['value'] : null;
        $paymentMethod = isset($f['payment_method']['value']) && $f['payment_method']['value'] !== '' ? $f['payment_method']['value'] : null;

        $parts = [];

        if ($gender) {
            $parts[] = Employee::getGenderOptions()[$gender] ?? $gender;
        }
        if ($status) {
            $parts[] = Employee::getStatusOptions()[$status] ?? $status;
        }
        if ($birthMonth) {
            $parts[] = 'Cumpleaños en '.(Employee::getMonthOptions()[$birthMonth] ?? $birthMonth);
        }
        if ($deptId) {
            $parts[] = 'Depto.: '.(Department::find($deptId)?->name ?? $deptId);
        }
        if ($contractType) {
            $parts[] = Contract::getTypeOptions()[$contractType] ?? $contractType;
        }
        if ($paymentMethod) {
            $parts[] = Employee::getPaymentMethodOptions()[$paymentMethod] ?? $paymentMethod;
        }

        return $parts ? implode(' · ', $parts) : 'Todos los empleados';
    }

    /**
     * Acciones de exportación (PDF y Excel) con selector de columnas y filtros activos.
     *
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        $columnOptions = EmployeeReportExport::availableColumns();
        $columnDefaults = EmployeeReportExport::defaultColumns();
        $orientationThreshold = 8;

        if (Company::active()->count() <= 1) {
            unset($columnOptions['company_name']);
            $columnDefaults = array_values(array_diff($columnDefaults, ['company_name']));
        }

        if (Branch::whereHas('company', fn ($q) => $q->active())->count() <= 1) {
            unset($columnOptions['branch_name']);
            $columnDefaults = array_values(array_diff($columnDefaults, ['branch_name']));
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
                    [$companyId, $branchId, $gender, $birthMonth, $status, $departmentId, $contractType, $paymentMethod] = $this->resolveActiveFilters();

                    $params = array_filter([
                        'companyId' => $companyId,
                        'branchId' => $branchId,
                        'gender' => $gender,
                        'birthMonth' => $birthMonth,
                        'status' => $status,
                        'departmentId' => $departmentId,
                        'contractType' => $contractType,
                        'paymentMethod' => $paymentMethod,
                    ], fn ($v) => $v !== null);

                    $params['columns'] = implode(',', $data['columns']);
                    $params['orientation'] = $data['orientation'] ?? 'portrait';

                    $url = route('employees.report.pdf', $params);
                    $this->js("window.open('".addslashes($url)."', '_blank')");
                }),

            Action::make('export')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->modalHeading('Exportar reporte de empleados')
                ->modalDescription('Seleccione las columnas a incluir en el archivo Excel.')
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
                    [$companyId, $branchId, $gender, $birthMonth, $status, $departmentId, $contractType, $paymentMethod] = $this->resolveActiveFilters();

                    Notification::make()
                        ->success()
                        ->title('Exportación iniciada')
                        ->body('El archivo se descargará en breve.')
                        ->send();

                    return Excel::download(
                        new EmployeeReportExport(
                            $companyId, $branchId, $gender, $birthMonth, $status,
                            $departmentId, $contractType, $paymentMethod,
                            $data['columns']
                        ),
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
            ->filtersFormColumns(4)
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

                TextColumn::make('age')
                    ->label('Edad')
                    ->getStateUsing(fn ($record) => $record->birth_date ? $record->birth_date->age.' años' : '—')
                    ->sortable(query: fn (Builder $query, string $direction) => $query
                        ->orderBy('employees.birth_date', $direction === 'asc' ? 'desc' : 'asc')),

                TextColumn::make('birthday')
                    ->label('Cumpleaños')
                    ->getStateUsing(function ($record) {
                        if (! $record->birth_date) {
                            return '—';
                        }

                        return $record->birth_date->day.' de '.$record->birth_date->locale('es')->isoFormat('MMMM');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('hire_date')
                    ->label('Fecha ingreso')
                    ->getStateUsing(fn ($record) => $record->hire_date)
                    ->date('d/m/Y')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('contracts.start_date', $direction)),

                TextColumn::make('years_of_service')
                    ->label('Antigüedad')
                    ->getStateUsing(function ($record) {
                        if (! $record->hire_date || $record->status !== 'active') {
                            return '—';
                        }
                        $hire = \Carbon\Carbon::parse($record->hire_date);
                        $years = (int) $hire->diffInYears(now());
                        if ($years >= 1) {
                            return $years.' año'.($years !== 1 ? 's' : '');
                        }
                        $months = (int) $hire->diffInMonths(now());
                        if ($months >= 1) {
                            return $months.' mes'.($months !== 1 ? 'es' : '');
                        }
                        $days = (int) $hire->diffInDays(now());

                        return $days.' día'.($days !== 1 ? 's' : '');
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
                    ->getStateUsing(fn ($record) => $record->salary
                        ? 'Gs. '.number_format((float) $record->salary, 0, ',', '.')
                        : '—'
                    )
                    ->alignRight()
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('contracts.salary', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('contract_type')
                    ->label('Tipo contrato')
                    ->getStateUsing(fn ($record) => Contract::getTypeOptions()[$record->contract_type] ?? '—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_method')
                    ->label('Método de pago')
                    ->getStateUsing(fn ($record) => Employee::getPaymentMethodOptions()[$record->payment_method] ?? '—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('position_name')
                    ->label('Cargo')
                    ->getStateUsing(fn ($record) => $record->position_name ?? '—')
                    ->sortable(),

                TextColumn::make('department_name')
                    ->label('Departamento')
                    ->getStateUsing(fn ($record) => $record->department_name ?? '—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('branch_name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-storefront')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->visible(fn () => Branch::whereHas('company', fn ($q) => $q->active())->count() > 1),

                TextColumn::make('company_name')
                    ->label('Empresa')
                    ->icon('heroicon-o-building-office-2')
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => Company::active()->count() > 1)
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->getStateUsing(fn ($record) => $record->status_label)
                    ->badge()
                    ->color(fn ($record) => $record->status_color),

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
     * Query base: Employee con join a branches, companies, contrato activo, posición y departamento.
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
                'contracts.type as contract_type',
                'contracts.payment_method',
                'positions.name as position_name',
                'departments.name as department_name',
                DB::raw('TIMESTAMPDIFF(YEAR, contracts.start_date, CURDATE()) AS years_of_service'),
                DB::raw('MONTH(employees.birth_date) AS birth_month'),
            ])
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->join('companies', 'companies.id', '=', 'branches.company_id')
            ->leftJoin('contracts', function ($join) {
                $join->on('contracts.employee_id', '=', 'employees.id')
                    ->where('contracts.status', '=', 'active');
            })
            ->leftJoin('positions', 'positions.id', '=', 'contracts.position_id')
            ->leftJoin('departments', 'departments.id', '=', 'contracts.department_id');
    }

    /**
     * Filtros: empresa, sucursal, departamento, tipo contrato, método de pago, género, cumpleaños, estado.
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
                    : $query);
        }

        return array_merge($filters, [
            SelectFilter::make('branch_id')
                ->label('Sucursal')
                ->options(fn () => Branch::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->query(fn (Builder $query, array $data) => $data['value']
                    ? $query->where('employees.branch_id', $data['value'])
                    : $query),

            SelectFilter::make('department_id')
                ->label('Departamento')
                ->options(fn () => Department::orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->query(fn (Builder $query, array $data) => $data['value']
                    ? $query->where('contracts.department_id', $data['value'])
                    : $query),

            SelectFilter::make('contract_type')
                ->label('Tipo de contrato')
                ->options(Contract::getTypeOptions())
                ->native(false)
                ->query(fn (Builder $query, array $data) => filled($data['value'])
                    ? $query->where('contracts.type', $data['value'])
                    : $query),

            SelectFilter::make('payment_method')
                ->label('Método de pago')
                ->options(Employee::getPaymentMethodOptions())
                ->native(false)
                ->query(fn (Builder $query, array $data) => filled($data['value'])
                    ? $query->where('contracts.payment_method', $data['value'])
                    : $query),

            SelectFilter::make('gender')
                ->label('Género')
                ->options(Employee::getGenderOptions())
                ->native(false)
                ->query(fn (Builder $query, array $data) => filled($data['value'])
                    ? $query->where('employees.gender', $data['value'])
                    : $query),

            SelectFilter::make('birth_month')
                ->label('Mes de cumpleaños')
                ->options(Employee::getMonthOptions())
                ->native(false)
                ->query(fn (Builder $query, array $data) => filled($data['value'])
                    ? $query->whereRaw('MONTH(employees.birth_date) = ?', [$data['value']])
                    : $query),

            SelectFilter::make('status')
                ->label('Estado')
                ->options(Employee::getStatusOptions())
                ->native(false)
                ->default('active')
                ->query(fn (Builder $query, array $data) => filled($data['value'])
                    ? $query->where('employees.status', $data['value'])
                    : $query),
        ]);
    }

    /**
     * Extrae los valores activos de los filtros para pasarlos al export/PDF.
     *
     * @return array{int|null, int|null, string|null, int|null, string|null, int|null, string|null, string|null}
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
            isset($f['department_id']['value']) && $f['department_id']['value'] !== '' ? (int) $f['department_id']['value'] : null,
            isset($f['contract_type']['value']) && $f['contract_type']['value'] !== '' ? $f['contract_type']['value'] : null,
            isset($f['payment_method']['value']) && $f['payment_method']['value'] !== '' ? $f['payment_method']['value'] : null,
        ];
    }
}
