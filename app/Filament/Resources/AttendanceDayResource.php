<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceDayResource\Pages;
use App\Filament\Resources\AttendanceDayResource\RelationManagers;
use App\Models\AttendanceDay;
use App\Services\AttendanceCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\CreateAction;
use Illuminate\Support\Carbon;

class AttendanceDayResource extends Resource
{
    protected static ?string $model = AttendanceDay::class;
    protected static ?string $navigationLabel = 'Asistencias';
    protected static ?string $label = 'asistencia';
    protected static ?string $pluralLabel = 'asistencias';
    protected static ?string $slug = 'asistencias';
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $navigationGroup = 'Asistencias';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información Básica')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship(
                                name: 'employee',
                                modifyQueryUsing: fn(Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->first_name} {$record->last_name} (CI: {$record->ci})")
                            ->searchable(['first_name', 'last_name', 'ci'])
                            ->native(false)
                            ->required()
                            ->preload()
                            ->disabled(fn(string $operation) => $operation === 'edit')
                            ->columnSpanFull(),

                        DatePicker::make('date')
                            ->label('Fecha')
                            ->required()
                            ->maxDate(now())
                            ->native(false)
                            ->disabled(fn(string $operation) => $operation === 'edit'),

                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'present' => 'Presente',
                                'absent' => 'Ausente',
                                'on_leave' => 'De permiso',
                                'holiday' => 'Feriado',
                                'weekend' => 'Fin de semana',
                            ])
                            ->native(false)
                            ->required(),

                        Placeholder::make('is_calculated_info')
                            ->label('Estado de Cálculo')
                            ->content(
                                fn(?AttendanceDay $record) => $record && $record->is_calculated
                                    ? '✅ Calculado el ' . $record->calculated_at?->format('d/m/Y H:i')
                                    : '⏳ Pendiente de calcular'
                            )
                            ->visible(fn(string $operation) => $operation === 'edit'),
                    ])
                    ->columns(3),

                Section::make('Horarios')
                    ->schema([
                        TimePicker::make('check_in_time')
                            ->label('Entrada marcada')
                            ->seconds(false)
                            ->native(false)
                            ->readOnly(),

                        TimePicker::make('check_out_time')
                            ->label('Salida marcada')
                            ->seconds(false)
                            ->native(false)
                            ->readOnly(),

                        TextInput::make('late_minutes')
                            ->label('Minutos tarde')
                            ->numeric()
                            ->suffix('min')
                            ->readOnly(),

                        TimePicker::make('expected_check_in')
                            ->label('Entrada esperada')
                            ->seconds(false)
                            ->native(false)
                            ->readOnly(),

                        TimePicker::make('expected_check_out')
                            ->label('Salida esperada')
                            ->seconds(false)
                            ->native(false)
                            ->readOnly(),

                        TextInput::make('early_leave_minutes')
                            ->label('Salida anticipada (min)')
                            ->numeric()
                            ->suffix('min')
                            ->readOnly(),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Horas')
                    ->schema([
                        TextInput::make('expected_hours')
                            ->label('Horas esperadas')
                            ->numeric()
                            ->suffix('hrs')
                            ->readOnly(),

                        TextInput::make('total_hours')
                            ->label('Horas trabajadas')
                            ->numeric()
                            ->suffix('hrs')
                            ->readOnly(),

                        TextInput::make('net_hours')
                            ->label('Horas netas')
                            ->numeric()
                            ->suffix('hrs')
                            ->readOnly(),

                        TextInput::make('break_minutes')
                            ->label('Descanso tomado (min)')
                            ->numeric()
                            ->suffix('min')
                            ->readOnly(),

                        TextInput::make('expected_break_minutes')
                            ->label('Descanso esperado (min)')
                            ->numeric()
                            ->suffix('min')
                            ->readOnly(),

                        TextInput::make('extra_hours')
                            ->label('Horas extra')
                            ->numeric()
                            ->suffix('hrs')
                            ->readOnly(),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Configuración')
                    ->schema([
                        Toggle::make('anomaly_flag')
                            ->label('Marcar anomalía')
                            ->inline(false),

                        Toggle::make('manual_adjustment')
                            ->label('Ajustado manualmente')
                            ->inline(false)
                            ->disabled(),

                        Toggle::make('overtime_approved')
                            ->label('Aprobar horas extra')
                            ->inline(false)
                            ->visible(fn(?AttendanceDay $record) => $record && $record->extra_hours > 0),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->maxLength(500)
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->numeric()
                    ->copyable()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employee.first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.position.name')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employee.position.department.name')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'present' => 'Presente',
                        'absent' => 'Ausente',
                        'on_leave' => 'De permiso',
                        default => 'Desconocido',
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'present' => 'success',
                        'absent' => 'danger',
                        'on_leave' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn($state) => match ($state) {
                        'present' => 'heroicon-o-check-circle',
                        'absent' => 'heroicon-o-x-circle',
                        'on_leave' => 'heroicon-o-document-text',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_calculated')
                    ->label('Calculado')
                    ->badge()
                    ->formatStateUsing(fn(bool $state) => $state ? 'Sí' : 'No')
                    ->color(fn(bool $state) => $state ? 'success' : 'warning')
                    ->icon(fn(bool $state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock')
                    ->tooltip(
                        fn(AttendanceDay $record) => $record->calculated_at
                            ? 'Calculado: ' . $record->calculated_at->format('d/m/Y H:i')
                            : 'Aún no calculado'
                    )
                    ->sortable(),
                TextColumn::make('check_in_time')
                    ->label('Entrada')
                    ->time('H:i')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color(fn(AttendanceDay $record) => $record->late_minutes > 0 ? 'danger' : 'success')
                    ->tooltip(
                        fn(AttendanceDay $record) => $record->late_minutes > 0
                            ? "Tarde: {$record->late_minutes} min"
                            : 'A tiempo'
                    )
                    ->toggleable(),
                TextColumn::make('check_out_time')
                    ->label('Salida')
                    ->time('H:i')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color(fn(AttendanceDay $record) => $record->early_leave_minutes > 0 ? 'warning' : 'success')
                    ->tooltip(
                        fn(AttendanceDay $record) => $record->early_leave_minutes > 0
                            ? "Salida anticipada: {$record->early_leave_minutes} min"
                            : 'A tiempo'
                    )
                    ->toggleable(),
                TextColumn::make('total_hours')
                    ->label('Horas')
                    ->icon('heroicon-o-clock')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('late_minutes')
                    ->label('Min. Tarde')
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('extra_hours')
                    ->label('Horas Extra')
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'warning' : 'gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('is_extraordinary_work')
                    ->label('Extra.')
                    ->badge()
                    ->formatStateUsing(fn(bool $state) => $state ? 'Sí' : 'No')
                    ->color(fn(bool $state) => $state ? 'warning' : 'gray')
                    ->tooltip('Trabajo extraordinario (feriado/fin de semana)')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('anomaly_flag')
                    ->label('Anomalía')
                    ->badge()
                    ->formatStateUsing(fn(bool $state) => $state ? 'Sí' : 'No')
                    ->color(fn(bool $state) => $state ? 'danger' : 'success')
                    ->icon(fn(bool $state) => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('employee.ci')
                    ->label('CI')
                    ->placeholder('Seleccionar CI')
                    ->relationship('employee', 'ci')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Seleccionar estado')
                    ->options([
                        'present' => 'Presente',
                        'absent' => 'Ausente',
                        'on_leave' => 'De permiso',
                        'holiday' => 'Feriado',
                        'weekend' => 'Fin de semana',
                    ])
                    ->native(false)
                    ->multiple(),
                SelectFilter::make('is_calculated')
                    ->label('Estado de Cálculo')
                    ->placeholder('Todos')
                    ->options([
                        '1' => 'Calculados',
                        '0' => 'Sin calcular',
                    ])
                    ->native(false),
                TernaryFilter::make('anomaly_flag')
                    ->label('Con anomalías')
                    ->placeholder('Todos')
                    ->trueLabel('Sí')
                    ->falseLabel('No')
                    ->native(false),
                TernaryFilter::make('is_extraordinary_work')
                    ->label('Trabajo extraordinario')
                    ->placeholder('Todos')
                    ->trueLabel('Sí')
                    ->falseLabel('No')
                    ->native(false),
                TernaryFilter::make('on_vacation')
                    ->label('De vacaciones')
                    ->placeholder('Todos')
                    ->trueLabel('Sí')
                    ->falseLabel('No')
                    ->native(false),
                TernaryFilter::make('justified_absence')
                    ->label('Ausencia justificada')
                    ->placeholder('Todos')
                    ->trueLabel('Sí')
                    ->falseLabel('No')
                    ->native(false),
                SelectFilter::make('employee.branch_id')
                    ->label('Sucursal')
                    ->placeholder('Seleccionar sucursal')
                    ->relationship('employee.branch', 'name')
                    ->native(false)
                    ->searchable()
                    ->preload(),
                SelectFilter::make('employee.position.department_id')
                    ->label('Departamento')
                    ->placeholder('Seleccionar departamento')
                    ->relationship('employee.position.department', 'name')
                    ->native(false)
                    ->searchable()
                    ->preload(),
                Filter::make('date')
                    ->label('Rango de Fechas')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde')
                            ->native(false)
                            ->maxDate(now()),
                        DatePicker::make('to')
                            ->label('Hasta')
                            ->native(false)
                            ->maxDate(now())
                            ->afterOrEqual('from'),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date))
                            ->when($data['to'], fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('Desde ' . Carbon::parse($data['from'])->format('d/m/Y'))
                                ->removeField('from');
                        }
                        if ($data['to'] ?? null) {
                            $indicators[] = Indicator::make('Hasta ' . Carbon::parse($data['to'])->format('d/m/Y'))
                                ->removeField('to');
                        }
                        return $indicators;
                    }),
                Filter::make('late')
                    ->label('Llegadas tarde')
                    ->query(fn(Builder $query): Builder => $query->where('late_minutes', '>', 0))
                    ->toggle(),
                Filter::make('extra_hours')
                    ->label('Con horas extra')
                    ->query(fn(Builder $query): Builder => $query->where('extra_hours', '>', 0))
                    ->toggle(),
            ])
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filtros')
            )
            ->actions([
                ViewAction::make()
                    ->color('primary'),
                EditAction::make(),
                Action::make('export')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn(AttendanceDay $record) => route('attendance-days.export', ['attendance_day' => $record->id]))
                    ->openUrlInNewTab(),
                Action::make('calculate')
                    ->label(fn(AttendanceDay $record) => $record->is_calculated ? 'Recalcular' : 'Calcular')
                    ->icon('heroicon-o-calculator')
                    ->color(fn(AttendanceDay $record) => $record->is_calculated ? 'warning' : 'success')
                    ->tooltip(
                        fn(AttendanceDay $record) => $record->is_calculated
                            ? 'Última vez calculado: ' . $record->calculated_at?->diffForHumans()
                            : 'Este registro aún no ha sido calculado'
                    )
                    ->requiresConfirmation()
                    ->modalHeading(
                        fn(AttendanceDay $record) => $record->is_calculated
                            ? 'Recalcular asistencia'
                            : 'Calcular asistencia'
                    )
                    ->modalDescription(
                        fn(AttendanceDay $record) => $record->is_calculated
                            ? 'Este registro ya fue calculado el ' . $record->calculated_at?->format('d/m/Y H:i') . '. ¿Deseas recalcularlo?'
                            : 'Se calcularán todos los campos de asistencia para este registro.'
                    )
                    ->modalSubmitActionLabel(fn(AttendanceDay $record) => $record->is_calculated ? 'Sí, recalcular' : 'Sí, calcular')
                    ->action(function (AttendanceDay $record) {
                        try {
                            $wasCalculated = $record->is_calculated;

                            AttendanceCalculator::apply($record);
                            $record->save();

                            $action = $wasCalculated ? 'recalculado' : 'calculado';

                            $statusMessages = [
                                'present' => "✓ Empleado presente - Cálculos {$action}s",
                                'absent' => "⚠ Empleado ausente",
                                'on_leave' => "📋 Empleado con permiso/vacaciones",
                                'holiday' => "🎉 Día feriado",
                                'weekend' => "📅 Fin de semana",
                            ];

                            $message = $statusMessages[$record->status] ?? "Cálculo {$action}";

                            Notification::make()
                                ->title("¡Registro {$action} exitosamente!")
                                ->body($message)
                                ->success()
                                ->duration(5000)
                                ->send();
                        } catch (\Exception $e) {
                            Log::error("Error calculando AttendanceDay {$record->id}: {$e->getMessage()}", [
                                'trace' => $e->getTraceAsString()
                            ]);

                            Notification::make()
                                ->title('Error al calcular')
                                ->body('Ocurrió un error al procesar el registro. Revisa los logs para más detalles.')
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
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
                            ->withFilename('asistencias_' . now()->format('d_m_Y_H_i_s')),
                    ])
                    ->label('Exportar')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-tray'),
                BulkAction::make('calculate')
                    ->label('Calcular/Recalcular')
                    ->icon('heroicon-o-calculator')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Calcular asistencias seleccionadas')
                    ->modalDescription(function (Collection $records) {
                        $calculated = $records->where('is_calculated', true)->count();
                        $notCalculated = $records->where('is_calculated', false)->count();

                        return "Se procesarán {$records->count()} registro(s): {$notCalculated} sin calcular y {$calculated} para recalcular.";
                    })
                    ->modalSubmitActionLabel('Calcular')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records) {
                        $successful = 0;
                        $failed = 0;
                        $recalculated = 0;
                        $calculated = 0;

                        foreach ($records as $day) {
                            try {
                                $wasCalculated = $day->is_calculated;

                                AttendanceCalculator::apply($day);
                                $day->save();

                                $successful++;
                                $wasCalculated ? $recalculated++ : $calculated++;
                            } catch (\Exception $e) {
                                $failed++;
                                Log::error("Error calculando AttendanceDay {$day->id}: {$e->getMessage()}");
                            }
                        }

                        if ($failed > 0) {
                            Notification::make()
                                ->title('Cálculo completado con advertencias')
                                ->body("✓ Exitosos: {$successful} ({$calculated} nuevos, {$recalculated} recalculados) | ✗ Fallidos: {$failed}")
                                ->warning()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('¡Cálculo completado exitosamente!')
                                ->body("Se procesaron {$successful} registro(s): {$calculated} calculado(s) y {$recalculated} recalculado(s).")
                                ->success()
                                ->send();
                        }
                    }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Crear primer registro')
                    ->icon('heroicon-o-plus'),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceDays::route('/'),
            'view' => Pages\ViewAttendanceDay::route('/{record}'),
            'create' => Pages\CreateAttendanceDay::route('/create'),
            'edit' => Pages\EditAttendanceDay::route('/{record}/edit'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Fieldset::make('Información General')
                    ->schema([
                        TextEntry::make('date')
                            ->label('Fecha')
                            ->date('d/m/Y')
                            ->icon('heroicon-o-calendar'),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'present' => 'success',
                                'absent' => 'danger',
                                'on_leave' => 'warning',
                                'holiday' => 'info',
                                'weekend' => 'gray',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn($state) => match ($state) {
                                'present' => 'Presente',
                                'absent' => 'Ausente',
                                'on_leave' => 'De permiso',
                                'holiday' => 'Feriado',
                                'weekend' => 'Fin de semana',
                                default => 'Desconocido',
                            }),
                        TextEntry::make('is_calculated')
                            ->label('Estado de Cálculo')
                            ->badge()
                            ->color(fn($state) => $state ? 'success' : 'warning')
                            ->formatStateUsing(fn($state) => $state ? 'Calculado' : 'Pendiente')
                            ->icon(fn($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock'),
                        TextEntry::make('calculated_at')
                            ->label('Último Cálculo')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Nunca calculado')
                            ->icon('heroicon-o-calendar-days')
                            ->visible(fn($record) => $record->is_calculated),
                        TextEntry::make('is_extraordinary_work')
                            ->label('Trabajo Extraordinario')
                            ->badge()
                            ->color(fn($state) => $state ? 'warning' : 'gray')
                            ->formatStateUsing(fn($state) => $state ? 'Sí' : 'No')
                            ->icon(fn($state) => $state ? 'heroicon-o-star' : null)
                            ->visible(fn($record) => $record->is_extraordinary_work),
                        TextEntry::make('anomaly_flag')
                            ->label('Anomalía detectada')
                            ->badge()
                            ->color(fn($state) => $state ? 'danger' : 'success')
                            ->formatStateUsing(fn($state) => $state ? 'Sí' : 'No')
                            ->icon(fn($state) => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'),
                        TextEntry::make('manual_adjustment')
                            ->label('Ajustado manualmente')
                            ->badge()
                            ->color(fn($state) => $state ? 'info' : 'gray')
                            ->formatStateUsing(fn($state) => $state ? 'Sí' : 'No')
                            ->icon(fn($state) => $state ? 'heroicon-o-pencil-square' : null),
                        TextEntry::make('is_weekend')
                            ->label('Es domingo')
                            ->badge()
                            ->color(fn($state) => $state ? 'gray' : null)
                            ->formatStateUsing(fn($state) => $state ? 'Sí' : 'No')
                            ->visible(fn($record) => $record->is_weekend),
                        TextEntry::make('is_holiday')
                            ->label('Es feriado')
                            ->badge()
                            ->color(fn($state) => $state ? 'info' : null)
                            ->formatStateUsing(fn($state) => $state ? 'Sí' : 'No')
                            ->icon('heroicon-o-gift')
                            ->visible(fn($record) => $record->is_holiday),
                        TextEntry::make('on_vacation')
                            ->label('De vacaciones')
                            ->badge()
                            ->color(fn($state) => $state ? 'success' : null)
                            ->formatStateUsing(fn($state) => $state ? 'Sí' : 'No')
                            ->icon('heroicon-o-sun')
                            ->visible(fn($record) => $record->on_vacation),
                        TextEntry::make('justified_absence')
                            ->label('Ausencia justificada')
                            ->badge()
                            ->color(fn($state) => $state ? 'warning' : null)
                            ->formatStateUsing(fn($state) => $state ? 'Sí' : 'No')
                            ->icon('heroicon-o-document-text')
                            ->visible(fn($record) => $record->justified_absence),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->columnSpanFull()
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->visible(fn($record) => !empty($record->notes)),
                    ])->columns(3),

                Fieldset::make('Empleado')
                    ->schema([
                        TextEntry::make('employee.ci')
                            ->label('CI')
                            ->icon('heroicon-o-identification'),
                        TextEntry::make('employee.first_name')
                            ->label('Nombre')
                            ->icon('heroicon-o-user'),
                        TextEntry::make('employee.last_name')
                            ->label('Apellido')
                            ->icon('heroicon-o-user'),
                        TextEntry::make('employee.branch.name')
                            ->label('Sucursal')
                            ->icon('heroicon-o-building-office'),
                        TextEntry::make('employee.position.name')
                            ->label('Cargo')
                            ->icon('heroicon-o-briefcase'),
                        TextEntry::make('employee.position.department.name')
                            ->label('Departamento')
                            ->icon('heroicon-o-building-office-2'),
                    ])->columns(3),

                Fieldset::make('Tiempos de Entrada')
                    ->schema([
                        TextEntry::make('expected_check_in')
                            ->label('Entrada esperada')
                            ->icon('heroicon-o-clock')
                            ->placeholder('No definida'),
                        TextEntry::make('check_in_time')
                            ->label('Entrada marcada')
                            ->icon('heroicon-o-arrow-right-on-rectangle')
                            ->color(fn($record) => $record->late_minutes > 0 ? 'danger' : 'success')
                            ->weight(fn($record) => $record->late_minutes > 0 ? 'bold' : null)
                            ->placeholder('Sin marcar'),
                        TextEntry::make('late_minutes')
                            ->label('Minutos tarde')
                            ->badge()
                            ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                            ->formatStateUsing(fn($state) => $state > 0 ? "{$state} min" : 'A tiempo')
                            ->icon(fn($state) => $state > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle'),
                    ])->columns(3)
                    ->visible(fn($record) => $record->status === 'present' || $record->check_in_time),

                Fieldset::make('Tiempos de Salida')
                    ->schema([
                        TextEntry::make('expected_check_out')
                            ->label('Salida esperada')
                            ->icon('heroicon-o-clock')
                            ->placeholder('No definida'),
                        TextEntry::make('check_out_time')
                            ->label('Salida marcada')
                            ->icon('heroicon-o-arrow-left-on-rectangle')
                            ->color(fn($record) => $record->early_leave_minutes > 0 ? 'warning' : 'success')
                            ->weight(fn($record) => $record->early_leave_minutes > 0 ? 'bold' : null)
                            ->placeholder('Sin marcar'),
                        TextEntry::make('early_leave_minutes')
                            ->label('Minutos de salida anticipada')
                            ->badge()
                            ->color(fn($state) => $state > 0 ? 'warning' : 'success')
                            ->formatStateUsing(fn($state) => $state > 0 ? "{$state} min" : 'A tiempo')
                            ->icon(fn($state) => $state > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle'),
                    ])->columns(3)
                    ->visible(fn($record) => $record->status === 'present' || $record->check_out_time),

                Fieldset::make('Horas y Descansos')
                    ->schema([
                        TextEntry::make('expected_hours')
                            ->label('Horas esperadas')
                            ->icon('heroicon-o-clock')
                            ->suffix(' hrs')
                            ->placeholder('No definidas'),
                        TextEntry::make('total_hours')
                            ->label('Horas trabajadas')
                            ->icon('heroicon-o-calculator')
                            ->suffix(' hrs')
                            ->weight('bold')
                            ->color('primary')
                            ->placeholder('0 hrs'),
                        TextEntry::make('net_hours')
                            ->label('Horas netas')
                            ->icon('heroicon-o-check-badge')
                            ->suffix(' hrs')
                            ->weight('bold')
                            ->color('success')
                            ->placeholder('0 hrs'),
                        TextEntry::make('break_minutes')
                            ->label('Descanso tomado')
                            ->icon('heroicon-o-pause-circle')
                            ->suffix(' min')
                            ->color(fn($record) => $record->break_minutes > ($record->expected_break_minutes ?? 0) ? 'warning' : null)
                            ->placeholder('0 min'),
                        TextEntry::make('expected_break_minutes')
                            ->label('Descanso esperado')
                            ->icon('heroicon-o-clock')
                            ->suffix(' min')
                            ->placeholder('No definido'),
                        TextEntry::make('extra_hours')
                            ->label('Horas extra')
                            ->badge()
                            ->icon('heroicon-o-star')
                            ->color(fn($state) => $state > 0 ? 'warning' : 'gray')
                            ->formatStateUsing(fn($state) => $state > 0 ? "{$state} hrs" : 'Sin horas extra')
                            ->visible(fn($record) => $record->extra_hours > 0 || $record->overtime_approved),
                        TextEntry::make('overtime_approved')
                            ->label('Horas extra aprobadas')
                            ->badge()
                            ->color(fn($state) => $state ? 'success' : 'gray')
                            ->formatStateUsing(fn($state) => $state ? 'Aprobadas' : 'No aprobadas')
                            ->icon(fn($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->visible(fn($record) => $record->extra_hours > 0),
                    ])->columns(3)
                    ->visible(fn($record) => $record->status === 'present'),
            ]);
    }
}
