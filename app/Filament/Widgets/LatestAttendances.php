<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class LatestAttendances extends BaseWidget
{
    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Últimas marcaciones';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                \App\Models\AttendanceEvent::query()
                    ->with(['day.employee', 'day.employee.position', 'day.employee.branch'])
                    ->latest()
                    ->take(10) // Limitar a los últimos 10 eventos de asistencia
            )
            ->columns([
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label('Marcado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('day.employee.ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('day.employee.first_name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('day.employee.last_name')
                    ->label('Apellido')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('day.employee.position.name')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('day.employee.branch.name')
                    ->label('Sucursal')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Tipo de Evento')
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
                        'check_out' => 'info',
                        default => 'gray',
                    })
                    ->sortable()
            ])
            ->filters([
                // Filtro para sucursal
                Tables\Filters\SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->relationship('day.employee.branch', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->native(false)
            ])
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
}
