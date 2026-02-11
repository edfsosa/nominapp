<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceEvent;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Widget de tabla que muestra las últimas marcaciones de asistencia del día actual
 */
class LatestAttendances extends BaseWidget
{
    // Configuraciones del widget
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Últimas Marcaciones de Hoy';
    protected static ?string $description = 'Marcaciones del día actual en tiempo real';
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
                // Consulta optimizada para obtener las marcaciones del día actual, con relaciones cargadas y ordenadas por fecha de grabación
                AttendanceEvent::query()
                    ->whereDate('recorded_at', today())
                    ->whereNotNull('employee_id')
                    ->latest('recorded_at')
            )
            // Definición de columnas optimizadas
            ->columns([
                // Fecha y hora de la marcación, con formato personalizado, descripción relativa, ícono y opciones de ordenamiento y búsqueda
                TextColumn::make('recorded_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->description(fn($record) => $record->recorded_at->diffForHumans())
                    ->icon('heroicon-o-clock')
                    ->sortable()
                    ->searchable(),

                // Tipo de evento de asistencia, con formato personalizado, badges y colores
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

                // Nombre del empleado, con descripción de CI y optimización para búsqueda y ordenamiento
                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->description(fn($record) => $record->employee_ci ? 'CI: ' . $record->employee_ci : '')
                    ->sortable()
                    ->searchable()
                    ->weight('medium')
                    ->wrap()
                    ->placeholder('N/A'),

                // Nombre de la sucursal, con ícono y optimización para búsqueda y ordenamiento
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
            ->filters([
                // Filtro de empleado con relación optimizada, etiquetas personalizadas y búsqueda
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

                // Filtro de sucursal con relación optimizada, etiquetas personalizadas y búsqueda
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->placeholder('Todas las sucursales')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(false)  // Lazy loading
                    ->native(false)
                    ->multiple(),

                // Filtro de tipo de evento con opciones personalizadas y búsqueda
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
            ->actions([
                // Acción para ver el detalle de la marcación en un modal, con contenido personalizado y sin acción de submit
                ViewAction::make()
                    ->label('Ver')
                    ->modalHeading('Detalle de Marcación')
                    ->modalContent(fn($record) => view('filament.resources.attendance-day.relation-managers.event-detail', [
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->bulkActions([
                // Grupo de acciones masivas para exportar las marcaciones seleccionadas a Excel, con configuración personalizada del exportador y opciones de archivo
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
