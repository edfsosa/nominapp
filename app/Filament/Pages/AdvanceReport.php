<?php

namespace App\Filament\Pages;

use App\Exports\AdvanceReportExport;
use App\Models\Advance;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
 * Reporte de adelantos de salario: muestra cada adelanto individualmente,
 * filtrable por rango de fechas, empresa, sucursal, estado y empleado.
 */
class AdvanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Reporte de Adelantos';

    protected static ?string $navigationGroup = 'Nóminas';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.pages.advance-report';

    protected ?string $heading = 'Reporte de Adelantos';

    /**
     * Retorna el subheading dinámico con el período activo del filtro.
     */
    public function getSubheading(): ?string
    {
        $f = $this->tableFilters ?? [];
        $from = $f['period']['from_date'] ?? null;
        $to = $f['period']['to_date'] ?? null;

        if ($from && $to) {
            return 'Solicitudes del '.date('d/m/Y', strtotime($from)).' al '.date('d/m/Y', strtotime($to));
        }

        if ($from) {
            return 'Solicitudes desde el '.date('d/m/Y', strtotime($from));
        }

        return 'Solicitudes del mes '.now()->translatedFormat('F Y');
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
                    [$from, $to, $companyId, $branchId, $status, $employeeId] = $this->resolveActiveFilters();

                    return route('advances.report.pdf', array_filter([
                        'from' => $from,
                        'to' => $to,
                        'companyId' => $companyId,
                        'branchId' => $branchId,
                        'status' => $status,
                        'employeeId' => $employeeId,
                    ], fn ($v) => $v !== null));
                })
                ->openUrlInNewTab(),

            Action::make('export')
                ->label('Exportar Excel')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar reporte de adelantos')
                ->modalDescription('Se exportará una fila por adelanto con los filtros seleccionados.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    [$from, $to, $companyId, $branchId, $status, $employeeId] = $this->resolveActiveFilters();

                    Notification::make()
                        ->success()
                        ->title('Exportación iniciada')
                        ->body('El archivo se descargará en breve.')
                        ->send();

                    return Excel::download(
                        new AdvanceReportExport($from, $to, $companyId, $branchId, $status, $employeeId),
                        'adelantos_'.now()->format('Y_m_d_H_i').'.xlsx'
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

                TextColumn::make('amount')
                    ->label('Monto')
                    ->formatStateUsing(fn ($state) => 'Gs. '.number_format((float) $state, 0, ',', '.'))
                    ->badge()
                    ->color(fn ($record) => match ($record->status) {
                        'paid' => 'success',
                        'approved' => 'warning',
                        default => 'gray',
                    })
                    ->alignRight()
                    ->sortable(),

                TextColumn::make('payment_method')
                    ->label('Método de pago')
                    ->formatStateUsing(fn ($state) => Advance::getPaymentMethodLabel($state))
                    ->badge()
                    ->color(fn ($state) => Advance::getPaymentMethodColor($state))
                    ->icon(fn ($state) => Advance::getPaymentMethodIcon($state)),

                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn ($state) => Advance::getStatusLabel($state))
                    ->badge()
                    ->color(fn ($state) => Advance::getStatusColor($state))
                    ->icon(fn ($state) => Advance::getStatusIcon($state)),

                TextColumn::make('created_at')
                    ->label('Solicitud')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('approved_at')
                    ->label('Aprobado el')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approved_by_name')
                    ->label('Aprobado por')
                    ->getStateUsing(fn ($record) => $record->approved_by_name ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->emptyStateHeading('Sin adelantos para el período')
            ->emptyStateDescription('No hay registros de adelantos para los filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }

    /**
     * Query base: Advance con joins a employees, branches y users (aprobador).
     */
    private function buildQuery(): Builder
    {
        return Advance::query()
            ->select([
                'advances.id',
                'advances.employee_id',
                'advances.amount',
                'advances.status',
                'advances.payment_method',
                'advances.notes',
                'advances.created_at',
                'advances.approved_at',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                'branches.name as branch_name',
                'users.name as approved_by_name',
            ])
            ->join('employees', 'employees.id', '=', 'advances.employee_id')
            ->join('branches', 'branches.id', '=', 'employees.branch_id')
            ->leftJoin('users', 'users.id', '=', 'advances.approved_by_id');
    }

    /**
     * Filtros del reporte: período, empresa, sucursal, estado y empleado.
     *
     * @return array<int, mixed>
     */
    private function buildFilters(): array
    {
        return [
            Filter::make('period')
                ->label('Período')
                ->form([
                    DatePicker::make('from_date')
                        ->label('Desde')
                        ->default(now()->startOfMonth()),
                    DatePicker::make('to_date')
                        ->label('Hasta')
                        ->default(now()->endOfMonth()),
                ])
                ->columns(2)
                ->query(fn (Builder $query, array $data) => $query
                    ->when($data['from_date'] ?? null, fn ($q, $v) => $q->whereDate('advances.created_at', '>=', $v))
                    ->when($data['to_date'] ?? null, fn ($q, $v) => $q->whereDate('advances.created_at', '<=', $v))
                )
                ->indicateUsing(function (array $data): array {
                    $indicators = [];
                    if ($data['from_date'] ?? null) {
                        $indicators[] = 'Desde: '.date('d/m/Y', strtotime($data['from_date']));
                    }
                    if ($data['to_date'] ?? null) {
                        $indicators[] = 'Hasta: '.date('d/m/Y', strtotime($data['to_date']));
                    }

                    return $indicators;
                }),

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
                ->options(Advance::getStatusOptions())
                ->placeholder('Todos los estados')
                ->query(fn (Builder $query, array $data) => filled($data['value'])
                    ? $query->where('advances.status', $data['value'])
                    : $query
                ),

            SelectFilter::make('employee_id')
                ->label('Empleado')
                ->options(fn () => Employee::orderBy('last_name')
                    ->orderBy('first_name')
                    ->get()
                    ->mapWithKeys(fn ($e) => [$e->id => $e->last_name.', '.$e->first_name.' ('.$e->ci.')'])
                    ->all()
                )
                ->searchable()
                ->query(fn (Builder $query, array $data) => $data['value']
                    ? $query->where('advances.employee_id', $data['value'])
                    : $query
                ),
        ];
    }

    /**
     * Extrae los valores activos de los filtros para pasarlos al export/PDF.
     *
     * @return array{string|null, string|null, int|null, int|null, string|null, int|null}
     */
    private function resolveActiveFilters(): array
    {
        $f = $this->tableFilters ?? [];

        return [
            $f['period']['from_date'] ?? now()->startOfMonth()->toDateString(),
            $f['period']['to_date'] ?? now()->endOfMonth()->toDateString(),
            isset($f['company_id']['value']) && $f['company_id']['value'] !== '' ? (int) $f['company_id']['value'] : null,
            isset($f['branch_id']['value']) && $f['branch_id']['value'] !== '' ? (int) $f['branch_id']['value'] : null,
            isset($f['status']['value']) && $f['status']['value'] !== '' ? $f['status']['value'] : null,
            isset($f['employee_id']['value']) && $f['employee_id']['value'] !== '' ? (int) $f['employee_id']['value'] : null,
        ];
    }
}
