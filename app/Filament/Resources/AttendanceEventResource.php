<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceEventResource\Pages;
use App\Models\AttendanceEvent;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class AttendanceEventResource extends Resource
{
    protected static ?string $model = AttendanceEvent::class;
    protected static ?string $navigationLabel = 'Marcaciones';
    protected static ?string $label = 'Marcación';
    protected static ?string $pluralLabel = 'Marcaciones';
    protected static ?string $slug = 'marcaciones';
    protected static ?string $navigationIcon = 'heroicon-o-finger-print';
    protected static ?string $navigationGroup = 'Asistencias';
    protected static ?int $navigationSort = 2;
    
    public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with([
            'day.employee.branch',
            'day.employee.position.department'
        ]);
}


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recorded_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->description(fn($record) => $record->recorded_at->diffForHumans())
                    ->icon('heroicon-o-clock')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('event_type')
                    ->label('Tipo de evento')
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

                TextColumn::make('employee_info')
                    ->label('Empleado')
                    ->getStateUsing(
                        fn($record) =>
                        $record->day?->employee
                            ? $record->day->employee->first_name . ' ' . $record->day->employee->last_name
                            : 'N/A'
                    )
                    ->description(
                        fn($record) =>
                        $record->day?->employee
                            ? 'CI: ' . $record->day->employee->ci
                            : ''
                    )
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('day.employee.branch.name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-office-2')
                    ->badge()
                    ->color('info')
                    ->sortable()    
                    ->toggleable(),

                TextColumn::make('day.employee.position.name')
                    ->label('Cargo')
                    ->description(
                        fn($record) =>
                        $record->day?->employee?->position?->department?->name ?? ''
                    )
                    ->icon('heroicon-o-briefcase')
                    ->sortable()
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('location_display')
                    ->label('Ubicación')
                    ->getStateUsing(function ($record) {
                        if (!$record->location) {
                            return 'Sin ubicación';
                        }

                        try {
                            $location = is_string($record->location)
                                ? json_decode($record->location, true)
                                : $record->location;

                            if (json_last_error() !== JSON_ERROR_NONE) {
                                return 'Error en formato';
                            }

                            if (isset($location['lat']) && isset($location['lng'])) {
                                return sprintf(
                                    'Lat: %s, Lng: %s',
                                    number_format($location['lat'], 6, '.', ''),
                                    number_format($location['lng'], 6, '.', '')
                                );
                            }

                            if (is_array($location)) {
                                return collect($location)
                                    ->map(fn($value, $key) => "{$key}: {$value}")
                                    ->join(', ');
                            }

                            return is_string($location) ? $location : json_encode($location);
                        } catch (\Exception $e) {
                            return 'Error al procesar ubicación';
                        }
                    })
                    ->icon('heroicon-o-map-pin')
                    ->color('gray')
                    ->limit(50)
                    ->url(fn($record) => $record->location ? static::getGoogleMapsUrl($record->location) : null)
                    ->openUrlInNewTab()
                    ->placeholder('Sin ubicación')
                    ->toggleable(),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->filters([
                SelectFilter::make('day.employee_id')
                    ->label('Empleado')
                    ->placeholder('Todos los empleados')
                    ->relationship('day.employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(
                        fn($record) =>
                        $record->first_name . ' ' . $record->last_name . ' (CI: ' . $record->ci . ')'
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('day.employee.branch_id')
                    ->label('Sucursal')
                    ->placeholder('Todas las sucursales')
                    ->relationship('day.employee.branch', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('day.employee.position_id')
                    ->label('Cargo')
                    ->placeholder('Todos los cargos')
                    ->relationship('day.employee.position', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('event_type')
                    ->label('Tipo de evento')
                    ->placeholder('Todos los tipos')
                    ->options([
                        'check_in' => 'Entrada',
                        'break_start' => 'Inicio descanso',
                        'break_end' => 'Fin descanso',
                        'check_out' => 'Salida',
                    ])
                    ->native(false)
                    ->multiple(),

                Filter::make('recorded_at')
                    ->label('Fecha de marcación')
                    ->form([
                        DatePicker::make('recorded_from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->maxDate(now()),
                        DatePicker::make('recorded_until')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->maxDate(now()),
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
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['recorded_from'] ?? null) {
                            $indicators['recorded_from'] = 'Desde: ' . \Carbon\Carbon::parse($data['recorded_from'])->format('d/m/Y');
                        }

                        if ($data['recorded_until'] ?? null) {
                            $indicators['recorded_until'] = 'Hasta: ' . \Carbon\Carbon::parse($data['recorded_until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                Filter::make('today')
                    ->label('Solo hoy')
                    ->query(fn(Builder $query) => $query->whereDate('recorded_at', now()))
                    ->toggle(),
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
                                ->withFilename('marcaciones_' . now()->format('d_m_Y_H_i_s')),
                        ])
                        ->label('Exportar seleccionados')
                        ->color('success')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ])
            ->emptyStateHeading('No hay marcaciones registradas')
            ->emptyStateDescription('Las marcaciones aparecerán aquí cuando los empleados registren su asistencia')
            ->emptyStateIcon('heroicon-o-finger-print')
            ->poll('30s');
    }

    protected static function getGoogleMapsUrl($location): ?string
    {
        if (!$location) {
            return null;
        }

        try {
            $locationData = is_string($location)
                ? json_decode($location, true)
                : $location;

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            if (isset($locationData['lat']) && isset($locationData['lng'])) {
                $lat = (float) $locationData['lat'];
                $lng = (float) $locationData['lng'];

                // Validar que las coordenadas sean válidas
                if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    return sprintf(
                        'https://www.google.com/maps?q=%s,%s',
                        $lat,
                        $lng
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning('Error generando URL de Google Maps', [
                'location' => $location,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAttendanceEvents::route('/'),
        ];
    }
}
