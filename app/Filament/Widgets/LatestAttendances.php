<?php

namespace App\Filament\Widgets;

use App\Models\AttendanceEvent;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

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
                        'day.employee.branch'
                    ])
                    ->whereHas('day.employee', function (Builder $query) {
                        $query->where('status', 'active');
                    })
                    ->whereDate('recorded_at', '>=', now()->subDays(7)) // Últimos 7 días
                    ->latest('recorded_at')
                    ->limit(15)
            )
            ->columns([
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-clock')
                    ->iconColor('primary'),

                Tables\Columns\TextColumn::make('day.employee.ci')
                    ->label('CI')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('CI copiado'),

                Tables\Columns\TextColumn::make('day.employee.full_name')
                    ->label('Empleado')
                    ->searchable(['day.employee.first_name', 'day.employee.last_name'])
                    ->wrap(),

                Tables\Columns\TextColumn::make('day.employee.position.name')
                    ->label('Cargo')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('day.employee.position.department.name')
                    ->label('Departamento')
                    ->badge()
                    ->color('warning')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('day.employee.branch.name')
                    ->label('Sucursal')
                    ->badge()
                    ->color('primary')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('event_type')
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

                Tables\Columns\TextColumn::make('day.date')
                    ->label('Día')
                    ->date('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Tipo de Evento')
                    ->options([
                        'check_in'    => 'Entrada',
                        'check_out'   => 'Salida',
                        'break_start' => 'Inicio Descanso',
                        'break_end'   => 'Fin Descanso',
                    ])
                    ->native(false)
                    ->multiple(),

                Tables\Filters\SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(\App\Models\Branch::pluck('name', 'id'))
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

                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->options(\App\Models\Department::pluck('name', 'id'))
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

                Tables\Filters\Filter::make('today')
                    ->label('Solo Hoy')
                    ->query(fn(Builder $query) => $query->whereDate('recorded_at', today()))
                    ->toggle()
                    ->default(),
            ])
            ->actions([
                /*Tables\Actions\Action::make('view_day')
                    ->label('Ver Día')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn($record) => route('filament.admin.resources.attendance-days.view', [
                        'record' => $record->attendance_day_id
                    ])),*/
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Sin bulk actions
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
