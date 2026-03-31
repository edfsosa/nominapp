<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceMarkFailureResource\Pages;
use App\Models\AttendanceMarkFailure;
use App\Models\Branch;
use App\Models\Employee;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Filters\Indicator;

/** Resource de solo lectura para inspeccionar intentos fallidos de marcación de asistencia. */
class AttendanceMarkFailureResource extends Resource
{
    protected static ?string $model = AttendanceMarkFailure::class;

    protected static ?string $navigationLabel = 'Fallos de marcación';
    protected static ?string $label = 'fallo de marcación';
    protected static ?string $pluralLabel = 'fallos de marcación';
    protected static ?string $slug = 'fallos-marcacion';
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?string $navigationGroup = 'Asistencias';
    protected static ?int $navigationSort = 5;

    /** Este resource es de solo lectura — no expone formulario de creación/edición. */
    public static function form(\Filament\Forms\Form $form): \Filament\Forms\Form
    {
        return $form->schema([]);
    }

    /**
     * Tabla principal con columnas, filtros y acciones de fila.
     *
     * @param  Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->query(
                AttendanceMarkFailure::query()
                    ->with(['employee', 'branch'])
                    ->latest('occurred_at')
            )
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Fecha/hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->searchable(false),

                TextColumn::make('mode')
                    ->label('Modo')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => AttendanceMarkFailure::getModeLabel($state))
                    ->color(fn(string $state) => AttendanceMarkFailure::getModeColor($state)),

                TextColumn::make('failure_type')
                    ->label('Tipo de fallo')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => AttendanceMarkFailure::getFailureTypeLabel($state))
                    ->color(fn(string $state) => AttendanceMarkFailure::getFailureTypeColor($state))
                    ->searchable(),

                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->getStateUsing(fn(AttendanceMarkFailure $record) => $record->employee
                        ? "{$record->employee->first_name} {$record->employee->last_name}"
                        : '—'
                    )
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('employee', fn($q) => $q
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                        );
                    }),

                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('attempted_event_type')
                    ->label('Evento intentado')
                    ->badge()
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'check_in'    => 'Entrada',
                        'break_start' => 'Inicio descanso',
                        'break_end'   => 'Fin descanso',
                        'check_out'   => 'Salida',
                        default       => '—',
                    })
                    ->color(fn(?string $state) => match ($state) {
                        'check_in'    => 'success',
                        'break_start' => 'warning',
                        'break_end'   => 'warning',
                        'check_out'   => 'danger',
                        default       => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('failure_message')
                    ->label('Mensaje')
                    ->limit(60)
                    ->tooltip(fn(AttendanceMarkFailure $record) => $record->failure_message)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->default('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->filters([
                SelectFilter::make('mode')
                    ->label('Modo')
                    ->options([
                        'terminal' => 'Terminal',
                        'mobile'   => 'Móvil',
                        'unknown'  => 'Desconocido',
                    ]),

                SelectFilter::make('failure_type')
                    ->label('Tipo de fallo')
                    ->options(AttendanceMarkFailure::getFailureTypeOptions()),

                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name'),

                Filter::make('occurred_at')
                    ->label('Período')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde')
                            ->displayFormat('d/m/Y'),
                        DatePicker::make('until')
                            ->label('Hasta')
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'],  fn($q, $v) => $q->whereDate('occurred_at', '>=', $v))
                            ->when($data['until'], fn($q, $v) => $q->whereDate('occurred_at', '<=', $v));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from']) {
                            $indicators[] = Indicator::make('Desde: ' . \Illuminate\Support\Carbon::parse($data['from'])->format('d/m/Y'))
                                ->removeField('from');
                        }
                        if ($data['until']) {
                            $indicators[] = Indicator::make('Hasta: ' . \Illuminate\Support\Carbon::parse($data['until'])->format('d/m/Y'))
                                ->removeField('until');
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Action::make('diagnose')
                    ->label('Diagnóstico')
                    ->icon('heroicon-o-light-bulb')
                    ->color('warning')
                    ->modalHeading(fn(AttendanceMarkFailure $record) => 'Diagnóstico: ' . AttendanceMarkFailure::getFailureTypeLabel($record->failure_type))
                    ->modalContent(fn(AttendanceMarkFailure $record) => view(
                        'filament.modals.attendance-mark-failure-diagnosis',
                        ['record' => $record]
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),

                ViewAction::make(),
            ])
            ->bulkActions([])
            ->paginated([25, 50, 100]);
    }

    /**
     * Infolist con todos los detalles del intento fallido.
     *
     * @param  Infolist $infolist
     * @return Infolist
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoSection::make('Resumen')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('occurred_at')
                            ->label('Fecha y hora')
                            ->dateTime('d/m/Y H:i:s'),

                        TextEntry::make('mode')
                            ->label('Modo')
                            ->badge()
                            ->formatStateUsing(fn(string $state) => AttendanceMarkFailure::getModeLabel($state))
                            ->color(fn(string $state) => AttendanceMarkFailure::getModeColor($state)),

                        TextEntry::make('failure_type')
                            ->label('Tipo de fallo')
                            ->badge()
                            ->formatStateUsing(fn(string $state) => AttendanceMarkFailure::getFailureTypeLabel($state))
                            ->color(fn(string $state) => AttendanceMarkFailure::getFailureTypeColor($state)),

                        TextEntry::make('attempted_event_type')
                            ->label('Evento intentado')
                            ->badge()
                            ->formatStateUsing(fn(?string $state) => match ($state) {
                                'check_in'    => 'Entrada',
                                'break_start' => 'Inicio descanso',
                                'break_end'   => 'Fin descanso',
                                'check_out'   => 'Salida',
                                default       => '—',
                            })
                            ->color(fn(?string $state) => match ($state) {
                                'check_in'  => 'success',
                                'break_start', 'break_end' => 'warning',
                                'check_out' => 'danger',
                                default     => 'gray',
                            }),

                        TextEntry::make('failure_message')
                            ->label('Mensaje de error')
                            ->columnSpan(2),
                    ]),

                InfoSection::make('Empleado y Sucursal')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('employee.first_name')
                            ->label('Empleado')
                            ->getStateUsing(fn(AttendanceMarkFailure $record) => $record->employee
                                ? "{$record->employee->first_name} {$record->employee->last_name} (CI: {$record->employee->ci})"
                                : '— (no identificado)'
                            ),

                        TextEntry::make('branch.name')
                            ->label('Sucursal')
                            ->default('—'),
                    ]),

                InfoSection::make('Red y Ubicación')
                    ->icon('heroicon-o-map-pin')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ip_address')
                            ->label('Dirección IP')
                            ->default('—'),

                        TextEntry::make('location')
                            ->label('Coordenadas GPS')
                            ->getStateUsing(fn(AttendanceMarkFailure $record) => isset($record->location['lat'], $record->location['lng'])
                                ? "{$record->location['lat']}, {$record->location['lng']}"
                                : '—'
                            ),
                    ]),

                InfoSection::make('Metadatos adicionales')
                    ->icon('heroicon-o-code-bracket')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        KeyValueEntry::make('metadata')
                            ->label('')
                            ->columnSpanFull()
                            ->getStateUsing(fn(AttendanceMarkFailure $record) => $record->metadata ?? []),
                    ])
                    ->visible(fn(AttendanceMarkFailure $record) => !empty($record->metadata)),
            ]);
    }

    /** @return array<string, class-string> */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceMarkFailures::route('/'),
            'view'  => Pages\ViewAttendanceMarkFailure::route('/{record}'),
        ];
    }
}
