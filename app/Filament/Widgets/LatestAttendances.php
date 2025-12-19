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

class LatestAttendances extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Últimas Marcaciones';
    protected static ?string $description = 'Registros de entrada y salida más recientes';
    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                AttendanceEvent::query()
                    ->with([
                        'day.employee.position.department',
                        'day.employee.branch',
                        'day'
                    ])
                    ->whereHas('day.employee', function (Builder $query) {
                        $query->where('status', 'active');
                    })
                    ->whereDate('recorded_at', '>=', now()->subDays(7))
                    ->latest('recorded_at')
            )
            ->columns([
                TextColumn::make('recorded_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-clock')
                    ->iconColor('primary')
                    ->description(fn(AttendanceEvent $record) => 'Hace ' . $record->recorded_at->diffForHumans()),

                TextColumn::make('day.employee.full_name')
                    ->label('Empleado')
                    ->searchable(['first_name', 'last_name'])
                    ->wrap()
                    ->weight('medium')
                    ->description(fn(AttendanceEvent $record) => 'CI: ' . $record->day->employee->ci)
                    ->copyable()
                    ->copyableState(fn(AttendanceEvent $record) => $record->day->employee->ci)
                    ->copyMessage('CI copiado'),

                TextColumn::make('day.employee.position.name')
                    ->label('Cargo')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('day.employee.position.department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('warning')
                    ->toggleable(),

                TextColumn::make('day.employee.branch.name')
                    ->label('Sucursal')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),

                TextColumn::make('event_type')
                    ->label('Evento')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'check_in'    => 'Entrada',
                        'check_out'   => 'Salida',
                        'break_start' => 'Inicio Descanso',
                        'break_end'   => 'Fin Descanso',
                        default       => 'Otro',
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'check_in'    => 'success',
                        'check_out'   => 'danger',
                        'break_start' => 'warning',
                        'break_end'   => 'info',
                        default       => 'gray',
                    })
                    ->icon(fn($state) => match ($state) {
                        'check_in'    => 'heroicon-o-arrow-right-on-rectangle',
                        'check_out'   => 'heroicon-o-arrow-left-on-rectangle',
                        'break_start' => 'heroicon-o-pause',
                        'break_end'   => 'heroicon-o-play',
                        default       => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable(),

                TextColumn::make('day.date')
                    ->label('Día')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
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

                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(Branch::pluck('name', 'id'))
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

                SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->options(Department::pluck('name', 'id'))
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

                Filter::make('today')
                    ->label('Solo Hoy')
                    ->query(fn(Builder $query) => $query->whereDate('recorded_at', today()))
                    ->toggle()
                    ->default(),
            ])
            ->actions([
                // Sin acciones individuales
            ])
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
                                ->withFilename('ultimas_marcaciones_' . now()->format('d_m_Y_H_i_s')),
                        ])
                        ->label('Exportar seleccionados')
                        ->color('success')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->striped()
            ->paginated([10, 15, 25])
            ->defaultPaginationPageOption(10)
            ->poll('30s')
            ->emptyStateHeading('No hay marcaciones recientes')
            ->emptyStateDescription('Las marcaciones de asistencia aparecerán aquí en tiempo real.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
