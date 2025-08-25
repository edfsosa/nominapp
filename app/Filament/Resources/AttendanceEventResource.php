<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceEventResource\Pages;
use App\Models\AttendanceEvent;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class AttendanceEventResource extends Resource
{
    protected static ?string $model = AttendanceEvent::class;
    protected static ?string $navigationLabel = 'Marcaciones';
    protected static ?string $label = 'Marcación';
    protected static ?string $pluralLabel = 'Marcaciones';
    protected static ?string $slug = 'marcaciones';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'Asistencias';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label('Marcado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'check_in' => 'Entrada jornada',
                        'break_start' => 'Inicio descanso',
                        'break_end' => 'Fin descanso',
                        'check_out' => 'Salida jornada',
                        default => 'Desconocido',
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'check_in' => 'success',
                        'break_start' => 'warning',
                        'break_end' => 'warning',
                        'check_out' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('day.employee.ci')
                    ->label('CI')
                    ->numeric()
                    ->copyable()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('day.employee.first_name')
                    ->label('Nombre(s)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('day.employee.last_name')
                    ->label('Apellido(s)')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('day.employee.branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('day.employee.position.name')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('day.employee.position.department.name')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->label('Ubicación')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->defaultSort('recorded_at', 'desc')
            ->filters([
                SelectFilter::make('day.employee_id')
                    ->label('Empleado')
                    ->placeholder('Seleccionar empleado')
                    ->relationship('day.employee', 'first_name')
                    ->native(false),
                SelectFilter::make('day.employee.branch_id')
                    ->label('Sucursal')
                    ->placeholder('Seleccionar sucursal')
                    ->relationship('day.employee.branch', 'name')
                    ->native(false),
                SelectFilter::make('event_type')
                    ->label('Tipo')
                    ->placeholder('Seleccionar tipo')
                    ->options([
                        'check_in' => 'Entrada jornada',
                        'break_start' => 'Inicio descanso',
                        'break_end' => 'Fin descanso',
                        'check_out' => 'Salida jornada',
                    ])
                    ->native(false),
                Filter::make('recorded_at')
                    ->label('Fecha marcado')
                    ->form([
                        DatePicker::make('recorded_from')
                            ->label('Desde')
                            ->native(false)
                            ->closeOnDateSelection(),
                        DatePicker::make('recorded_until')
                            ->label('Hasta')
                            ->native(false)
                            ->closeOnDateSelection(),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['recorded_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('recorded_at', '>=', $date),
                            )
                            ->when(
                                $data['recorded_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('recorded_at', '<=', $date),
                            );
                    })
            ])
            ->actions([])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except([
                                'created_at',
                                'updated_at',
                            ])
                            ->withFilename('marcaciones_' . now()->format('d_m_Y_H_i_s')),
                    ])
                    ->label('Exportar')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-tray')
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAttendanceEvents::route('/'),
        ];
    }
}
