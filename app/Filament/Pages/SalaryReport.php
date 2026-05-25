<?php

namespace App\Filament\Pages;

use App\Exports\SalaryReportExport;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollPeriod;
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
 * Reporte de salarios: muestra un resumen por empleado para una planilla dada,
 * con desglose de percepciones, deducciones por tipo y neto a pagar.
 */
class SalaryReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Reporte de Salarios';

    protected static ?string $navigationGroup = 'Reportes';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.pages.salary-report';

    protected ?string $heading = 'Reporte de Salarios';

    /**
     * Aplica la planilla más reciente como default si la sesión aún no tiene filtro de período.
     */
    public function mount(): void
    {
        $this->tableFilters['period_id']['value'] ??= (string) (PayrollPeriod::latest()->value('id') ?? '');
    }

    /**
     * Subheading dinámico con el nombre de la planilla activa.
     */
    public function getSubheading(): ?string
    {
        $f = $this->tableFilters ?? [];
        $periodId = isset($f['period_id']['value']) && $f['period_id']['value'] !== ''
            ? (int) $f['period_id']['value']
            : null;

        if (! $periodId) {
            return 'Seleccione una planilla para ver el reporte';
        }

        $period = PayrollPeriod::find($periodId);

        return $period ? "Planilla: {$period->name}" : null;
    }

    /**
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
                    [$periodId, $companyId, $branchId, $status, $paymentMethod] = $this->resolveActiveFilters();

                    return route('salary-report.pdf', array_filter([
                        'periodId' => $periodId,
                        'companyId' => $companyId,
                        'branchId' => $branchId,
                        'status' => $status,
                        'paymentMethod' => $paymentMethod,
                    ], fn ($v) => $v !== null));
                })
                ->openUrlInNewTab(),

            Action::make('export_excel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar reporte de salarios')
                ->modalDescription('Se exportará una fila por empleado con los filtros seleccionados.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    [$periodId, $companyId, $branchId, $status, $paymentMethod] = $this->resolveActiveFilters();

                    Notification::make()
                        ->success()
                        ->title('Exportación iniciada')
                        ->body('El archivo se descargará en breve.')
                        ->send();

                    return Excel::download(
                        new SalaryReportExport($periodId, $companyId, $branchId, $status, $paymentMethod),
                        'salarios_'.now()->format('Y_m_d_H_i').'.xlsx'
                    );
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildQuery())
            ->filters($this->buildFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->persistFiltersInSession()
            ->defaultSort('employees.last_name', 'asc')
            ->paginated([25, 50, 100, 'all'])
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
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('position_name')
                    ->label('Cargo')
                    ->icon('heroicon-o-briefcase')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('base_salary')
                    ->label('Salario Base')
                    ->formatStateUsing(fn ($state) => 'Gs. '.number_format((float) $state, 0, ',', '.'))
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('total_perceptions')
                    ->label('+Percepciones')
                    ->formatStateUsing(fn ($state) => 'Gs. '.number_format((float) $state, 0, ',', '.'))
                    ->alignRight()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ips_amount')
                    ->label('IPS')
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Gs. '.number_format((float) $state, 0, ',', '.') : '—')
                    ->alignRight()
                    ->sortable()
                    ->color('warning'),

                TextColumn::make('loan_amount')
                    ->label('Prést./Adelantos')
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Gs. '.number_format((float) $state, 0, ',', '.') : '—')
                    ->alignRight()
                    ->sortable()
                    ->color('warning'),

                TextColumn::make('judicial_amount')
                    ->label('Judiciales')
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Gs. '.number_format((float) $state, 0, ',', '.') : '—')
                    ->alignRight()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('voluntary_amount')
                    ->label('Voluntarias')
                    ->formatStateUsing(fn ($state) => $state > 0 ? 'Gs. '.number_format((float) $state, 0, ',', '.') : '—')
                    ->alignRight()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_deductions')
                    ->label('-Deducciones')
                    ->formatStateUsing(fn ($state) => 'Gs. '.number_format((float) $state, 0, ',', '.'))
                    ->alignRight()
                    ->sortable()
                    ->color('danger'),

                TextColumn::make('net_salary')
                    ->label('Neto a Pagar')
                    ->formatStateUsing(fn ($state) => 'Gs. '.number_format((float) $state, 0, ',', '.'))
                    ->alignRight()
                    ->sortable()
                    ->weight('bold')
                    ->color('success'),

                TextColumn::make('payment_method')
                    ->label('Método')
                    ->formatStateUsing(fn (?string $state) => $state ? Payroll::getPaymentMethodLabels()[$state] ?? $state : '—')
                    ->badge()
                    ->color(fn (?string $state) => $state ? (Payroll::getPaymentMethodColors()[$state] ?? 'gray') : 'gray')
                    ->icon(fn (?string $state) => $state ? (Payroll::getPaymentMethodIcons()[$state] ?? null) : null),

                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (string $state) => Payroll::getStatusLabels()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state) => Payroll::getStatusColors()[$state] ?? 'gray')
                    ->icon(fn (string $state) => Payroll::getStatusIcons()[$state] ?? null),
            ])
            ->emptyStateHeading('Sin recibos para la planilla seleccionada')
            ->emptyStateDescription('Seleccione una planilla para ver el reporte de salarios.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    /**
     * Query base con subqueries para montos de deducciones por tipo.
     */
    private function buildQuery(): Builder
    {
        return Payroll::query()
            ->select([
                'payrolls.id',
                'payrolls.employee_id',
                'payrolls.base_salary',
                'payrolls.total_perceptions',
                'payrolls.total_deductions',
                'payrolls.net_salary',
                'payrolls.status',
                'payrolls.payment_method',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                DB::raw('(SELECT positions.name FROM contracts INNER JOIN positions ON positions.id = contracts.position_id WHERE contracts.employee_id = employees.id AND contracts.status = \'active\' LIMIT 1) as position_name'),
                DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'legal\') as ips_amount'),
                DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'loan\') as loan_amount'),
                DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'judicial\') as judicial_amount'),
                DB::raw('(SELECT COALESCE(SUM(pi.amount),0) FROM payroll_items pi WHERE pi.payroll_id = payrolls.id AND pi.type = \'deduction\' AND pi.deduction_type = \'voluntary\') as voluntary_amount'),
            ])
            ->join('employees', 'employees.id', '=', 'payrolls.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id');
    }

    /**
     * @return array<int, mixed>
     */
    private function buildFilters(): array
    {
        $filters = [
            SelectFilter::make('period_id')
                ->label('Planilla')
                ->options(
                    fn () => PayrollPeriod::orderByDesc('start_date')
                        ->get()
                        ->mapWithKeys(fn ($p) => [$p->id => $p->name])
                        ->toArray()
                )
                ->searchable()
                ->query(
                    fn (Builder $query, array $data) => $data['value']
                        ? $query->where('payrolls.payroll_period_id', $data['value'])
                        : $query->whereRaw('1=0')
                ),
        ];

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

            SelectFilter::make('status')
                ->label('Estado')
                ->options(Payroll::getStatusLabels())
                ->query(
                    fn (Builder $query, array $data) => $data['value']
                        ? $query->where('payrolls.status', $data['value'])
                        : $query
                ),

            SelectFilter::make('payment_method')
                ->label('Método de pago')
                ->options(Payroll::getPaymentMethodOptions())
                ->query(
                    fn (Builder $query, array $data) => $data['value']
                        ? $query->where('payrolls.payment_method', $data['value'])
                        : $query
                ),
        ]);
    }

    /**
     * Extrae los valores activos de los filtros para export/PDF.
     *
     * @return array{int|null, int|null, int|null, string|null, string|null}
     */
    private function resolveActiveFilters(): array
    {
        $f = $this->tableFilters ?? [];

        return [
            isset($f['period_id']['value']) && $f['period_id']['value'] !== '' ? (int) $f['period_id']['value'] : null,
            isset($f['company_id']['value']) && $f['company_id']['value'] !== '' ? (int) $f['company_id']['value'] : null,
            isset($f['branch_id']['value']) && $f['branch_id']['value'] !== '' ? (int) $f['branch_id']['value'] : null,
            isset($f['status']['value']) && $f['status']['value'] !== '' ? $f['status']['value'] : null,
            isset($f['payment_method']['value']) && $f['payment_method']['value'] !== '' ? $f['payment_method']['value'] : null,
        ];
    }
}
