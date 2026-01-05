<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceEvent;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Widget de tabla que muestra las últimas marcaciones de asistencia del día actual
 *
 */
class LatestAttendances extends BaseWidget
{
    /** Indica que el widget ocupará el ancho completo del contenedor */
    protected int | string | array $columnSpan = 'full';

    /** Título del widget mostrado en la interfaz */
    protected static ?string $heading = 'Últimas Marcaciones de Hoy';

    /** Descripción breve del widget */
    protected static ?string $description = 'Marcaciones del día actual en tiempo real';

    /** Orden de visualización del widget en el dashboard (menor número = mayor prioridad) */
    protected static ?int $sort = 3;

    /**
     * Configura la tabla del widget con columnas, filtros y acciones optimizadas
     *
     * @param Table $table Instancia de la tabla de Filament
     * @return Table Tabla configurada
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(
                AttendanceEvent::query()
                    // Solo marcaciones de hoy (mucho más rápido que 7 días)
                    ->whereDate('recorded_at', today())
                    // Filtrar solo empleados activos usando campo desnormalizado
                    ->whereNotNull('employee_id')
                    // Ordenar por más reciente primero
                    ->latest('recorded_at')
            )
            // Definición de columnas optimizadas
            ->columns([
                // Columna: Fecha y hora de la marcación
                TextColumn::make('recorded_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->description(fn($record) => $record->recorded_at->diffForHumans())
                    ->icon('heroicon-o-clock')
                    ->sortable()
                    ->searchable(),

                // Columna: Tipo de evento (entrada, salida, descanso)
                TextColumn::make('event_type')
                    ->label('Tipo de Evento')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'check_in' => 'Entrada',
                        'break_start' => 'Inicio descanso',
                        'break_end' => 'Fin descanso',
                        'check_out' => 'Salida',
                        default => 'Desconocido',
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'check_in' => 'success',
                        'break_start' => 'warning',
                        'break_end' => 'info',
                        'check_out' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn($state) => match ($state) {
                        'check_in' => 'heroicon-o-arrow-right-circle',
                        'break_start' => 'heroicon-o-pause-circle',
                        'break_end' => 'heroicon-o-play-circle',
                        'check_out' => 'heroicon-o-arrow-left-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable()
                    ->searchable(),

                // OPTIMIZACIÓN: Usa campo desnormalizado employee_name
                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->description(fn($record) => $record->employee_ci ? 'CI: ' . $record->employee_ci : '')
                    ->sortable()
                    ->searchable()
                    ->weight('medium')
                    ->wrap()
                    ->placeholder('N/A'),

                // OPTIMIZACIÓN: Usa campo desnormalizado branch_name
                TextColumn::make('branch_name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-office-2')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->placeholder('N/A'),
            ])
            // Filtros optimizados
            ->filters([
                // OPTIMIZACIÓN: Usa relación directa employee en lugar de day.employee
                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->placeholder('Todos los empleados')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(
                        fn($record) =>
                        $record->first_name . ' ' . $record->last_name . ' (CI: ' . $record->ci . ')'
                    )
                    ->searchable()
                    ->preload(false)  // Lazy loading
                    ->native(false)
                    ->multiple(),

                // OPTIMIZACIÓN: Usa relación directa branch
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->placeholder('Todas las sucursales')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(false)  // Lazy loading
                    ->native(false)
                    ->multiple(),

                // Filtro: Tipo de evento
                SelectFilter::make('event_type')
                    ->label('Tipo de Evento')
                    ->placeholder('Todos los tipos')
                    ->options([
                        'check_in' => 'Entrada',
                        'break_start' => 'Inicio descanso',
                        'break_end' => 'Fin descanso',
                        'check_out' => 'Salida',
                    ])
                    ->native(false)
                    ->multiple(),
            ])
            // Acciones individuales por fila
            ->actions([
                ViewAction::make()
                    ->label('Ver')
                    ->modalHeading('Detalle de Marcación')
                    ->modalContent(fn($record) => view('filament.resources.attendance-day.relation-managers.event-detail', [
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            // Acciones masivas (bulk actions)
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->except([
                                    'created_at',
                                    'updated_at',
                                ])
                                ->withFilename('marcaciones_hoy_' . now()->format('d_m_Y_H_i_s')),
                        ])
                        ->label('Exportar seleccionados')
                        ->color('success')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('No hay marcaciones de hoy')
            ->emptyStateDescription('Las marcaciones del día aparecerán aquí automáticamente.')
            ->emptyStateIcon('heroicon-o-finger-print');
    }
}
