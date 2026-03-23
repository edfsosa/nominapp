<?php

namespace App\Filament\Pages;

use App\Exports\AttendanceReportDetailExport;
use App\Exports\AttendanceReportSummaryExport;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use Carbon\Carbon;
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
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Página de reporte de asistencias por período.
 *
 * Muestra un resumen agregado por empleado con totales de días trabajados, horas netas,
 * horas extra y tardanzas. Filtrable por período, empresa, sucursal y departamento.
 * Permite exportar tanto un resumen (1 fila/empleado) como el detalle (1 fila/día).
 */
class AttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Reporte de Asistencias';
    protected static ?string $navigationGroup = 'Asistencias';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.pages.attendance-report';
    protected ?string $heading = 'Reporte de Asistencias';
    protected ?string $subheading = 'Resumen por empleado · período seleccionado';

    /**
     * Define las acciones del encabezado: exportar resumen y detalle en Excel.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_summary')
                ->label('Exportar Resumen')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar resumen a Excel')
                ->modalDescription('Se descargará un archivo Excel con una fila por empleado y los totales del período filtrado.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    [$from, $to, $companyId, $branchId, $deptId] = $this->resolveActiveFilters();

                    Notification::make()
                        ->success()
                        ->title('Exportación iniciada')
                        ->body('El archivo se descargará en breve.')
                        ->send();

                    return Excel::download(
                        new AttendanceReportSummaryExport($from, $to, $companyId, $branchId, $deptId),
                        'asistencia_resumen_' . now()->format('Y_m_d') . '.xlsx'
                    );
                }),

            Action::make('export_detail')
                ->label('Exportar Detalle')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar detalle a Excel')
                ->modalDescription('Se descargará un archivo Excel con una fila por empleado por día (detalle completo del período).')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    [$from, $to, $companyId, $branchId, $deptId] = $this->resolveActiveFilters();

                    Notification::make()
                        ->success()
                        ->title('Exportación iniciada')
                        ->body('El archivo se descargará en breve.')
                        ->send();

                    return Excel::download(
                        new AttendanceReportDetailExport($from, $to, $companyId, $branchId, $deptId),
                        'asistencia_detalle_' . now()->format('Y_m_d') . '.xlsx'
                    );
                }),
        ];
    }

    /**
     * Define la tabla con columnas agregadas por empleado y filtros de período/organización.
     *
     * @param  Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->query($this->buildQuery())
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->getStateUsing(fn($record) => $record->last_name . ', ' . $record->first_name)
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderBy('employees.last_name', $direction)->orderBy('employees.first_name', $direction))
                    ->searchable(query: fn(Builder $query, string $search) => $query->where(
                        fn($q) => $q->where('employees.first_name', 'like', "%{$search}%")
                                    ->orWhere('employees.last_name', 'like', "%{$search}%")
                    ))
                    ->weight('medium'),

                TextColumn::make('ci')
                    ->label('CI')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('branch_name')
                    ->label('Sucursal')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('department_name')
                    ->label('Departamento')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('position_name')
                    ->label('Cargo')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('days_present')
                    ->label('Presentes')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn($state) => $state > 0 ? 'success' : 'gray'),

                TextColumn::make('days_absent')
                    ->label('Ausencias')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn($state) => $state > 0 ? 'danger' : 'gray'),

                TextColumn::make('days_leave')
                    ->label('Licencias')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn($state) => $state > 0 ? 'warning' : 'gray'),

                TextColumn::make('total_net_hours')
                    ->label('Horas Netas')
                    ->suffix(' h')
                    ->numeric(2)
                    ->alignEnd()
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderByRaw("total_net_hours {$direction}")),

                TextColumn::make('total_extra_diurnas')
                    ->label('HE Diurnas')
                    ->suffix(' h')
                    ->numeric(2)
                    ->alignEnd()
                    ->color(fn($state) => $state > 0 ? 'success' : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_extra_nocturnas')
                    ->label('HE Nocturnas')
                    ->suffix(' h')
                    ->numeric(2)
                    ->alignEnd()
                    ->color(fn($state) => $state > 0 ? 'info' : null)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_late_minutes')
                    ->label('Tardanza')
                    ->suffix(' min')
                    ->numeric()
                    ->alignEnd()
                    ->color(fn($state) => $state > 0 ? 'danger' : null)
                    ->toggleable(),

                TextColumn::make('total_anomalies')
                    ->label('Anomalías')
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'warning' : 'gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('period')
                    ->label('Período')
                    ->form([
                        DatePicker::make('from_date')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->default(now()->startOfMonth()),

                        DatePicker::make('to_date')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->default(now()->endOfMonth()),
                    ])
                    ->columns(2)
                    ->query(fn(Builder $query, array $data) => $query
                        ->when($data['from_date'] ?? null, fn($q, $v) => $q->where('ad.date', '>=', $v))
                        ->when($data['to_date'] ?? null, fn($q, $v) => $q->where('ad.date', '<=', $v))
                    )
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from_date'] ?? null) {
                            $indicators[] = 'Desde: ' . Carbon::parse($data['from_date'])->format('d/m/Y');
                        }
                        if ($data['to_date'] ?? null) {
                            $indicators[] = 'Hasta: ' . Carbon::parse($data['to_date'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),

                SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->options(fn() => Company::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn(Builder $query, array $data) => $data['value']
                        ? $query->whereExists(fn($sub) => $sub->selectRaw(1)
                            ->from('branches')
                            ->whereColumn('branches.id', 'employees.branch_id')
                            ->where('branches.company_id', $data['value']))
                        : $query
                    ),

                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn() => Branch::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn(Builder $query, array $data) => $data['value']
                        ? $query->where('employees.branch_id', $data['value'])
                        : $query
                    ),

                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->options(fn() => Department::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->query(fn(Builder $query, array $data) => $data['value']
                        ? $query->whereExists(fn($sub) => $sub->selectRaw(1)
                            ->from('contracts')
                            ->whereColumn('contracts.employee_id', 'employees.id')
                            ->where('contracts.status', 'active')
                            ->where('contracts.department_id', $data['value']))
                        : $query
                    ),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->persistFiltersInSession()
            ->defaultSort('employees.last_name', 'asc')
            ->paginated([25, 50, 100])
            ->striped()
            ->emptyStateHeading('Sin registros de asistencia')
            ->emptyStateDescription('No hay marcaciones en el período y filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    /**
     * Construye la query base con datos de asistencia agregados por empleado.
     *
     * Usa un JOIN con attendance_days para agregar métricas por período.
     * Los filtros de la tabla se aplican como WHERE sobre esta query antes del GROUP BY.
     *
     * @return Builder
     */
    private function buildQuery(): Builder
    {
        return Employee::query()
            ->select([
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                DB::raw('(SELECT b.name FROM branches b WHERE b.id = employees.branch_id) AS branch_name'),
                DB::raw("(SELECT p.name FROM contracts c INNER JOIN positions p ON p.id = c.position_id WHERE c.employee_id = employees.id AND c.status = 'active' ORDER BY c.start_date DESC LIMIT 1) AS position_name"),
                DB::raw("(SELECT d.name FROM contracts c INNER JOIN positions p ON p.id = c.position_id INNER JOIN departments d ON d.id = p.department_id WHERE c.employee_id = employees.id AND c.status = 'active' ORDER BY c.start_date DESC LIMIT 1) AS department_name"),
                DB::raw("COALESCE(SUM(CASE WHEN ad.status = 'present'  THEN 1 ELSE 0 END), 0) AS days_present"),
                DB::raw("COALESCE(SUM(CASE WHEN ad.status = 'absent'   THEN 1 ELSE 0 END), 0) AS days_absent"),
                DB::raw("COALESCE(SUM(CASE WHEN ad.status = 'on_leave' THEN 1 ELSE 0 END), 0) AS days_leave"),
                DB::raw('ROUND(COALESCE(SUM(ad.net_hours), 0), 2)            AS total_net_hours'),
                DB::raw('ROUND(COALESCE(SUM(ad.extra_hours_diurnas), 0), 2)  AS total_extra_diurnas'),
                DB::raw('ROUND(COALESCE(SUM(ad.extra_hours_nocturnas), 0), 2) AS total_extra_nocturnas'),
                DB::raw('COALESCE(SUM(ad.late_minutes), 0)                   AS total_late_minutes'),
                DB::raw('COALESCE(SUM(CASE WHEN ad.anomaly_flag = 1 THEN 1 ELSE 0 END), 0) AS total_anomalies'),
            ])
            ->join('attendance_days as ad', 'ad.employee_id', '=', 'employees.id')
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'employees.ci', 'employees.branch_id');
    }

    /**
     * Extrae los valores activos de los filtros de la tabla para pasarlos a los exports.
     *
     * @return array{string|null, string|null, int|null, int|null, int|null}
     */
    private function resolveActiveFilters(): array
    {
        $f = $this->tableFilters ?? [];

        return [
            $f['period']['from_date'] ?? null,
            $f['period']['to_date'] ?? null,
            isset($f['company_id']['value'])    ? (int) $f['company_id']['value']    : null,
            isset($f['branch_id']['value'])     ? (int) $f['branch_id']['value']     : null,
            isset($f['department_id']['value']) ? (int) $f['department_id']['value'] : null,
        ];
    }
}
