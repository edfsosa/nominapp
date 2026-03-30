<?php

namespace App\Filament\Pages;

use App\Exports\AbsenceReportDetailExport;
use App\Exports\AbsenceReportSummaryExport;
use App\Exports\AttendanceReportDetailExport;
use App\Exports\AttendanceReportSummaryExport;
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
 * Página unificada de reportes de asistencias y ausencias por período.
 *
 * Presenta dos tabs: "Asistencias" (resumen de marcaciones por empleado)
 * y "Ausencias" (resumen del flujo de gestión de ausencias por empleado).
 * Los filtros de período, empresa, sucursal y departamento son compartidos.
 */
class AttendanceReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Reportes de Asistencia';
    protected static ?string $navigationGroup = 'Asistencias';
    protected static ?int    $navigationSort  = 99;
    protected static string  $view            = 'filament.pages.attendance-report';
    protected ?string $heading                = 'Reportes de Asistencia';

    /** @var string Tab activo: 'attendance' o 'absence'. */
    public string $activeTab = 'attendance';

    /**
     * Cambia el tab activo y reinicia la tabla para aplicar la nueva query y columnas.
     *
     * @param  string $tab 'attendance' | 'absence'
     * @return void
     */
    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    /**
     * Retorna el subheading dinámico según el tab activo.
     *
     * @return string
     */
    public function getSubheading(): ?string
    {
        return $this->activeTab === 'attendance'
            ? 'Asistencias · resumen por empleado · período seleccionado'
            : 'Ausencias · resumen por empleado · período seleccionado';
    }

    /**
     * Define las acciones del encabezado según el tab activo.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        if ($this->activeTab === 'attendance') {
            return [
                Action::make('export_attendance_summary')
                    ->label('Exportar Resumen')
                    ->icon('heroicon-o-table-cells')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Exportar resumen de asistencias')
                    ->modalDescription('Una fila por empleado con los totales del período filtrado.')
                    ->modalSubmitActionLabel('Sí, exportar')
                    ->action(function () {
                        [$from, $to, $companyId, $branchId, $deptId] = $this->resolveActiveFilters();
                        Notification::make()->success()->title('Exportación iniciada')->body('El archivo se descargará en breve.')->send();
                        return Excel::download(
                            new AttendanceReportSummaryExport($from, $to, $companyId, $branchId, $deptId),
                            'asistencia_resumen_' . now()->format('Y_m_d') . '.xlsx'
                        );
                    }),

                Action::make('export_attendance_detail')
                    ->label('Exportar Detalle')
                    ->icon('heroicon-o-document-text')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Exportar detalle de asistencias')
                    ->modalDescription('Una fila por día por empleado con todos los valores calculados.')
                    ->modalSubmitActionLabel('Sí, exportar')
                    ->action(function () {
                        [$from, $to, $companyId, $branchId, $deptId] = $this->resolveActiveFilters();
                        Notification::make()->success()->title('Exportación iniciada')->body('El archivo se descargará en breve.')->send();
                        return Excel::download(
                            new AttendanceReportDetailExport($from, $to, $companyId, $branchId, $deptId),
                            'asistencia_detalle_' . now()->format('Y_m_d') . '.xlsx'
                        );
                    }),
            ];
        }

        return [
            Action::make('export_absence_summary')
                ->label('Exportar Resumen')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar resumen de ausencias')
                ->modalDescription('Una fila por empleado con totales pendientes, justificadas, injustificadas y deducciones.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    [$from, $to, $companyId, $branchId, $deptId] = $this->resolveActiveFilters();
                    Notification::make()->success()->title('Exportación iniciada')->body('El archivo se descargará en breve.')->send();
                    return Excel::download(
                        new AbsenceReportSummaryExport($from, $to, $companyId, $branchId, $deptId),
                        'ausencias_resumen_' . now()->format('Y_m_d') . '.xlsx'
                    );
                }),

            Action::make('export_absence_detail')
                ->label('Exportar Detalle')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar detalle de ausencias')
                ->modalDescription('Una fila por ausencia con fecha, motivo, estado, revisión y deducción.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    [$from, $to, $companyId, $branchId, $deptId] = $this->resolveActiveFilters();
                    Notification::make()->success()->title('Exportación iniciada')->body('El archivo se descargará en breve.')->send();
                    return Excel::download(
                        new AbsenceReportDetailExport($from, $to, $companyId, $branchId, $deptId),
                        'ausencias_detalle_' . now()->format('Y_m_d') . '.xlsx'
                    );
                }),
        ];
    }

    /**
     * Define la tabla según el tab activo, con filtros compartidos.
     *
     * @param  Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        $base = $table
            ->query($this->activeTab === 'attendance' ? $this->buildAttendanceQuery() : $this->buildAbsenceQuery())
            ->filters($this->sharedFilters(), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->persistFiltersInSession()
            ->defaultSort('employees.last_name', 'asc')
            ->paginated([25, 50, 100])
            ->striped();

        return $this->activeTab === 'attendance'
            ? $this->applyAttendanceTable($base)
            : $this->applyAbsenceTable($base);
    }

    /**
     * Aplica columnas y estado vacío para el tab de asistencias.
     *
     * @param  Table $table
     * @return Table
     */
    private function applyAttendanceTable(Table $table): Table
    {
        return $table
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
            ->emptyStateHeading('Sin registros de asistencia')
            ->emptyStateDescription('No hay marcaciones en el período y filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    /**
     * Aplica columnas y estado vacío para el tab de ausencias.
     *
     * @param  Table $table
     * @return Table
     */
    private function applyAbsenceTable(Table $table): Table
    {
        return $table
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

                TextColumn::make('total_absences')
                    ->label('Total')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderByRaw("total_absences {$direction}")),

                TextColumn::make('total_pending')
                    ->label('Pendientes')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn($state) => $state > 0 ? 'warning' : 'gray')
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderByRaw("total_pending {$direction}")),

                TextColumn::make('total_justified')
                    ->label('Justificadas')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn($state) => $state > 0 ? 'success' : 'gray')
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderByRaw("total_justified {$direction}")),

                TextColumn::make('total_unjustified')
                    ->label('Injustificadas')
                    ->numeric()
                    ->alignCenter()
                    ->color(fn($state) => $state > 0 ? 'danger' : 'gray')
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderByRaw("total_unjustified {$direction}")),

                TextColumn::make('total_deduction_amount')
                    ->label('Deducciones (Gs.)')
                    ->numeric(0, ',', '.')
                    ->alignEnd()
                    ->color(fn($state) => $state > 0 ? 'danger' : null)
                    ->sortable(query: fn(Builder $query, string $direction) => $query->orderByRaw("total_deduction_amount {$direction}")),
            ])
            ->emptyStateHeading('Sin ausencias registradas')
            ->emptyStateDescription('No hay ausencias en el período y filtros seleccionados.')
            ->emptyStateIcon('heroicon-o-x-circle');
    }

    /**
     * Filtros compartidos entre ambos tabs: período, empresa, sucursal y departamento.
     *
     * @return array
     */
    private function sharedFilters(): array
    {
        return [
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
                    ->when($data['to_date']   ?? null, fn($q, $v) => $q->where('ad.date', '<=', $v))
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
        ];
    }

    /**
     * Query de asistencias: agrega marcaciones por empleado desde attendance_days.
     *
     * @return Builder
     */
    private function buildAttendanceQuery(): Builder
    {
        return Employee::query()
            ->select([
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                DB::raw('(SELECT b.name FROM branches b WHERE b.id = employees.branch_id) AS branch_name'),
                DB::raw("(SELECT d.name FROM contracts c INNER JOIN positions p ON p.id = c.position_id INNER JOIN departments d ON d.id = p.department_id WHERE c.employee_id = employees.id AND c.status = 'active' ORDER BY c.start_date DESC LIMIT 1) AS department_name"),
                DB::raw("COALESCE(SUM(CASE WHEN ad.status = 'present'  THEN 1 ELSE 0 END), 0) AS days_present"),
                DB::raw("COALESCE(SUM(CASE WHEN ad.status = 'absent'   THEN 1 ELSE 0 END), 0) AS days_absent"),
                DB::raw("COALESCE(SUM(CASE WHEN ad.status = 'on_leave' THEN 1 ELSE 0 END), 0) AS days_leave"),
                DB::raw('ROUND(COALESCE(SUM(ad.net_hours), 0), 2)             AS total_net_hours'),
                DB::raw('ROUND(COALESCE(SUM(ad.extra_hours_diurnas), 0), 2)   AS total_extra_diurnas'),
                DB::raw('ROUND(COALESCE(SUM(ad.extra_hours_nocturnas), 0), 2) AS total_extra_nocturnas'),
                DB::raw('COALESCE(SUM(ad.late_minutes), 0)                    AS total_late_minutes'),
                DB::raw('COALESCE(SUM(CASE WHEN ad.anomaly_flag = 1 THEN 1 ELSE 0 END), 0) AS total_anomalies'),
            ])
            ->join('attendance_days as ad', 'ad.employee_id', '=', 'employees.id')
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'employees.ci', 'employees.branch_id');
    }

    /**
     * Query de ausencias: agrega registros de la tabla absences por empleado.
     *
     * @return Builder
     */
    private function buildAbsenceQuery(): Builder
    {
        return Employee::query()
            ->select([
                'employees.id',
                'employees.first_name',
                'employees.last_name',
                'employees.ci',
                DB::raw('(SELECT b.name FROM branches b WHERE b.id = employees.branch_id) AS branch_name'),
                DB::raw("(SELECT d.name FROM contracts c INNER JOIN positions p ON p.id = c.position_id INNER JOIN departments d ON d.id = p.department_id WHERE c.employee_id = employees.id AND c.status = 'active' ORDER BY c.start_date DESC LIMIT 1) AS department_name"),
                DB::raw('COUNT(abs.id) AS total_absences'),
                DB::raw("COALESCE(SUM(CASE WHEN abs.status = 'pending'     THEN 1 ELSE 0 END), 0) AS total_pending"),
                DB::raw("COALESCE(SUM(CASE WHEN abs.status = 'justified'   THEN 1 ELSE 0 END), 0) AS total_justified"),
                DB::raw("COALESCE(SUM(CASE WHEN abs.status = 'unjustified' THEN 1 ELSE 0 END), 0) AS total_unjustified"),
                DB::raw('COALESCE(SUM(CASE WHEN abs.employee_deduction_id IS NOT NULL THEN ed.custom_amount ELSE 0 END), 0) AS total_deduction_amount'),
            ])
            ->join('absences as abs', 'abs.employee_id', '=', 'employees.id')
            ->join('attendance_days as ad', 'ad.id', '=', 'abs.attendance_day_id')
            ->leftJoin('employee_deductions as ed', 'ed.id', '=', 'abs.employee_deduction_id')
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name', 'employees.ci', 'employees.branch_id');
    }

    /**
     * Extrae los valores activos de los filtros para pasarlos a los exports.
     *
     * @return array{string|null, string|null, int|null, int|null, int|null}
     */
    private function resolveActiveFilters(): array
    {
        $f = $this->tableFilters ?? [];

        return [
            $f['period']['from_date'] ?? null,
            $f['period']['to_date']   ?? null,
            isset($f['company_id']['value'])    ? (int) $f['company_id']['value']    : null,
            isset($f['branch_id']['value'])     ? (int) $f['branch_id']['value']     : null,
            isset($f['department_id']['value']) ? (int) $f['department_id']['value'] : null,
        ];
    }
}
