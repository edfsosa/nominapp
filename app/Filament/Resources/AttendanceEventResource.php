<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\AttendanceEvent;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\BulkActionGroup;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\AttendanceEventResource\Pages;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

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
        return parent::getEloquentQuery();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('event_type')
                    ->label('Tipo de Evento')
                    ->options(AttendanceEvent::getEventTypeOptions())
                    ->native(false)
                    ->required()
                    ->columnSpanFull(),
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

                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->description(fn($record) => $record->employee_ci ? 'CI: ' . $record->employee_ci : '')
                    ->sortable()
                    ->searchable()
                    ->weight('medium')
                    ->wrap()
                    ->placeholder('N/A'),

                TextColumn::make('branch_name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-office-2')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->placeholder('N/A'),

                TextColumn::make('location_display')
                    ->label('Ubicación')
                    ->icon('heroicon-o-map-pin')
                    ->color('gray')
                    ->limit(50)
                    ->url(fn($record) => $record->google_maps_url)
                    ->openUrlInNewTab()
                    ->placeholder('Sin ubicación')
                    ->toggleable(),
            ])
            ->defaultSort('recorded_at', 'desc')
            ->filters([
                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->placeholder('Todos los empleados')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(
                        fn($record) =>
                        $record->first_name . ' ' . $record->last_name . ' (CI: ' . $record->ci . ')'
                    )
                    ->searchable()
                    ->preload(false)
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->placeholder('Todas las sucursales')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(false)
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
                            $indicators['recorded_from'] = 'Desde: ' . Carbon::parse($data['recorded_from'])->format('d/m/Y');
                        }

                        if ($data['recorded_until'] ?? null) {
                            $indicators['recorded_until'] = 'Hasta: ' . Carbon::parse($data['recorded_until'])->format('d/m/Y');
                        }

                        return $indicators;
                    }),

                Filter::make('today')
                    ->label('Solo hoy')
                    ->query(fn(Builder $query) => $query->whereDate('recorded_at', now()))
                    ->toggle(),
            ])
            ->actions([
                ViewAction::make()
                    ->modalHeading('Detalle de Marcación')
                    ->modalContent(fn($record) => view('filament.resources.attendance-day.relation-managers.event-detail', [
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
                
                EditAction::make()
                    ->modalHeading('Editar Marcación')
                    ->successNotificationTitle('Marcación actualizada exitosamente'),

                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->modalHeading('Eliminar Marcación')
                    ->modalDescription('¿Está seguro de que desea eliminar esta marcación? Esta acción no se puede deshacer.')
                    ->successNotificationTitle('Marcación eliminada exitosamente'),
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
                        ->label('Exportar a Excel')
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ])
            ->emptyStateHeading('No hay marcaciones registradas')
            ->emptyStateDescription('Las marcaciones aparecerán aquí cuando los empleados registren su asistencia')
            ->emptyStateIcon('heroicon-o-finger-print')
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAttendanceEvents::route('/'),
        ];
    }
}
