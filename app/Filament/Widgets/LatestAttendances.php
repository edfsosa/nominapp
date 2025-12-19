<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceEvent;
use App\Models\Branch;
use App\Models\Department;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Widget de tabla que muestra las últimas marcaciones de asistencia
 *
 * Este widget muestra los eventos de asistencia más recientes (entradas, salidas, descansos)
 * de empleados activos con filtros por empleado, tipo de evento, sucursal y departamento.
 * Incluye actualización automática cada 30 segundos y exportación a Excel.
 */
class LatestAttendances extends BaseWidget
{
    /** Indica que el widget ocupará el ancho completo del contenedor */
    protected int | string | array $columnSpan = 'full';

    /** Título del widget mostrado en la interfaz */
    protected static ?string $heading = 'Últimas Marcaciones';

    /** Descripción breve del widget */
    protected static ?string $description = 'Registros de entrada y salida más recientes';

    /** Orden de visualización del widget en el dashboard (menor número = mayor prioridad) */
    protected static ?int $sort = 3;

    /**
     * Configura la tabla del widget con columnas, filtros y acciones
     *
     * @param Table $table Instancia de la tabla de Filament
     * @return Table Tabla configurada
     */
    public function table(Table $table): Table
    {
        return $table
            // Consulta base: obtener eventos de asistencia de los últimos 7 días
            ->query(
                AttendanceEvent::query()
                    // Eager loading para evitar el problema N+1
                    ->with([
                        'day.employee.position.department', // Cargar departamento del cargo
                        'day.employee.branch',              // Cargar sucursal del empleado
                        'day'                               // Cargar día de asistencia
                    ])
                    // Filtrar solo empleados activos
                    ->whereHas('day.employee', function (Builder $query) {
                        $query->where('status', 'active');
                    })
                    // Marcaciones de los últimos 7 días
                    ->whereDate('recorded_at', '>=', now()->subDays(7))
                    // Ordenar por fecha más reciente primero
                    ->latest('recorded_at')
            )
            // Definición de columnas de la tabla
            ->columns([
                // Columna: Tipo de evento (entrada, salida, descanso)
                TextColumn::make('event_type')
                    ->label('Evento')
                    // Traducir los tipos de eventos al español
                    ->formatStateUsing(fn($state) => match ($state) {
                        'check_in'    => 'Entrada',
                        'check_out'   => 'Salida',
                        'break_start' => 'Inicio Descanso',
                        'break_end'   => 'Fin Descanso',
                        default       => 'Otro',
                    })
                    ->badge()
                    // Asignar colores según el tipo de evento
                    ->color(fn($state) => match ($state) {
                        'check_in'    => 'success',  // Verde para entrada
                        'check_out'   => 'danger',   // Rojo para salida
                        'break_start' => 'warning',  // Amarillo para inicio de descanso
                        'break_end'   => 'info',     // Azul para fin de descanso
                        default       => 'gray',
                    })
                    // Asignar iconos según el tipo de evento
                    ->icon(fn($state) => match ($state) {
                        'check_in'    => 'heroicon-o-arrow-right-on-rectangle',
                        'check_out'   => 'heroicon-o-arrow-left-on-rectangle',
                        'break_start' => 'heroicon-o-pause',
                        'break_end'   => 'heroicon-o-play',
                        default       => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),

                // Columna: Fecha y hora de la marcación
                TextColumn::make('recorded_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-clock')
                    ->iconColor('primary')
                    ->wrap()
                    // Mostrar tiempo relativo como descripción ("Hace 2 horas")
                    ->description(fn(AttendanceEvent $record) => 'Hace ' . $record->recorded_at->diffForHumans()),

                // Columna: Nombre completo del empleado
                TextColumn::make('day.employee.full_name')
                    ->label('Empleado')
                    // Buscar por first_name y last_name (columnas reales en BD)
                    ->searchable(['first_name', 'last_name'])
                    ->wrap()
                    ->weight('medium')
                    // Mostrar CI como descripción
                    ->description(fn(AttendanceEvent $record) => 'CI: ' . $record->day->employee->ci)
                    ->copyable()
                    // Al copiar, copiar el CI en lugar del nombre
                    ->copyableState(fn(AttendanceEvent $record) => $record->day->employee->ci)
                    ->copyMessage('CI copiado'),

                // Columna: Cargo del empleado
                TextColumn::make('day.employee.position.name')
                    ->label('Cargo')
                    // Mostrar el departamento como descripción
                    ->description(
                        fn($record) =>
                        $record->day?->employee?->position?->department?->name ?? ''
                    )
                    ->icon('heroicon-o-briefcase')
                    ->badge()
                    ->color('info')
                    ->wrap()
                    ->toggleable(), // Columna puede ocultarse/mostrarse

                // Columna: Sucursal del empleado
                TextColumn::make('day.employee.branch.name')
                    ->label('Sucursal')
                    // Mostrar el horario como descripción
                    ->description(
                        fn($record) =>
                        $record->day?->employee?->schedule?->name ?? ''
                    )
                    ->icon('heroicon-o-building-office-2')
                    ->badge()
                    ->color('primary')
                    ->wrap()
                    ->sortable()
                    ->toggleable(), // Columna puede ocultarse/mostrarse
            ])
            // Filtros de la tabla
            ->filters([
                // Filtro: Seleccionar empleados específicos
                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->options(
                        // Obtener lista única de empleados activos que tienen marcaciones
                        fn() => AttendanceEvent::query()
                            ->whereHas('day.employee', function (Builder $query) {
                                $query->where('status', 'active');
                            })
                            ->with('day.employee')
                            ->get()
                            ->pluck('day.employee.full_name', 'day.employee.id')
                            ->unique()
                            ->sort()
                    )
                    // Query personalizada para filtrar por IDs de empleados
                    ->query(function (Builder $query, array $data) {
                        if (filled($data['values'])) {
                            return $query->whereHas('day.employee', function (Builder $q) use ($data) {
                                $q->whereIn('id', $data['values']);
                            });
                        }
                    })
                    ->searchable()
                    ->preload()  // Cargar opciones al abrir el filtro
                    ->native(false)  // Usar componente personalizado de Filament
                    ->multiple(),  // Permitir selección múltiple

                // Filtro: Tipo de evento (entrada, salida, descansos)
                SelectFilter::make('event_type')
                    ->label('Tipo de Evento')
                    ->options([
                        'check_in'    => 'Entrada',
                        'check_out'   => 'Salida',
                        'break_start' => 'Inicio Descanso',
                        'break_end'   => 'Fin Descanso',
                    ])
                    ->native(false)
                    ->multiple(),

                // Filtro: Sucursal del empleado
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(Branch::pluck('name', 'id'))
                    // Query personalizada para filtrar por sucursal a través de la relación
                    ->query(function (Builder $query, array $data) {
                        if (filled($data['values'])) {
                            return $query->whereHas('day.employee', function (Builder $q) use ($data) {
                                $q->whereIn('branch_id', $data['values']);
                            });
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->multiple(),

                // Filtro: Departamento del cargo del empleado
                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->options(Department::pluck('name', 'id'))
                    // Query personalizada para filtrar por departamento a través de relaciones
                    ->query(function (Builder $query, array $data) {
                        if (filled($data['values'])) {
                            return $query->whereHas('day.employee.position', function (Builder $q) use ($data) {
                                $q->whereIn('department_id', $data['values']);
                            });
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->multiple(),

                // Filtro toggle: Mostrar solo marcaciones de hoy
                Filter::make('today')
                    ->label('Solo Hoy')
                    ->query(fn(Builder $query) => $query->whereDate('recorded_at', today()))
                    ->toggle()
                    ->default(),  // Activado por defecto
            ])
            // Acciones individuales por fila (actualmente ninguna)
            ->actions([
                // Sin acciones individuales
            ])
            // Acciones masivas (bulk actions)
            ->bulkActions([
                BulkActionGroup::make([
                    // Acción: Exportar registros seleccionados a Excel
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()  // Tomar columnas de la tabla
                                ->except([
                                    'created_at',
                                    'updated_at',
                                ])
                                // Nombre de archivo con timestamp
                                ->withFilename('ultimas_marcaciones_' . now()->format('d_m_Y_H_i_s')),
                        ])
                        ->label('Exportar seleccionados')
                        ->color('success')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ])
            // Configuración de la tabla
            ->defaultSort('recorded_at', 'desc')  // Ordenar por fecha descendente por defecto
            ->paginated([10, 15, 25])  // Opciones de paginación
            ->defaultPaginationPageOption(10)  // 10 registros por página por defecto
            ->poll('30s')  // Actualizar automáticamente cada 30 segundos
            // Estado vacío de la tabla
            ->emptyStateHeading('No hay marcaciones recientes')
            ->emptyStateDescription('Las marcaciones de asistencia aparecerán aquí en tiempo real.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
