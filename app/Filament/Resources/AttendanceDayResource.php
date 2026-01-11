<?php

namespace App\Filament\Resources;

use App\Models\Branch;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\AttendanceDay;
use Illuminate\Support\Carbon;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Actions\Action;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use App\Services\AttendanceCalculator;
use Filament\Forms\Components\Section;
use Filament\Tables\Filters\Indicator;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Collection;
use Filament\Tables\Actions\DeleteBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\AttendanceDayResource\Pages;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Filament\Resources\AttendanceDayResource\RelationManagers;

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
                            ->options(AttendanceDay::getStatusOptions())
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
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['employee.branch', 'employee.position.department']))
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->getStateUsing(fn($record) => $record->employee ? "{$record->employee->first_name} {$record->employee->last_name}" : 'N/A')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->icon('heroicon-o-identification')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->tooltip('Haz clic para copiar')
                    ->copyMessage('Cédula copiada'),

                TextColumn::make('employee.branch.name')
                    ->label('Sucursal')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('employee.position.name')
                    ->label('Cargo')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('employee.position.department.name')
                    ->label('Departamento')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn($state) => AttendanceDay::getStatusLabel($state))
                    ->badge()
                    ->color(fn($state) => AttendanceDay::getStatusColor($state))
                    ->icon(fn($state) => AttendanceDay::getStatusIcon($state))
                    ->sortable(),

                TextColumn::make('is_calculated')
                    ->label('Calculado')
                    ->badge()
                    ->formatStateUsing(fn(bool $state) => $state ? 'Sí' : 'No')
                    ->color(fn(bool $state) => $state ? 'success' : 'warning')
                    ->icon(fn(bool $state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock')
                    ->tooltip(fn(AttendanceDay $record) => $record->getCalculationTooltip())
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('check_in_time')
                    ->label('Entrada')
                    ->time('H:i')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color(fn(AttendanceDay $record) => $record->getCheckInStatusColor())
                    ->tooltip(fn(AttendanceDay $record) => $record->getCheckInTooltip())
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('check_out_time')
                    ->label('Salida')
                    ->time('H:i')
                    ->icon('heroicon-o-arrow-left-on-rectangle')
                    ->color(fn(AttendanceDay $record) => $record->getCheckOutStatusColor())
                    ->tooltip(fn(AttendanceDay $record) => $record->getCheckOutTooltip())
                    ->toggleable()
                    ->sortable(),

                TextColumn::make('late_minutes')
                    ->label('Tardanza')
                    ->suffix(' min')
                    ->default(0)
                    ->color(fn($state) => $state > 0 ? 'danger' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_hours')
                    ->label('Horas trabajadas')
                    ->suffix(' hrs')
                    ->default(0)
                    ->numeric(2)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Filter::make('today')
                    ->label('Hoy')
                    ->query(fn(Builder $query): Builder => $query->whereDate('date', now()))
                    ->toggle()
                    ->default(true),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->full_name} (CI: {$record->ci})")
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->preload(false)
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('branch')
                    ->label('Sucursal')
                    ->placeholder('Todas las sucursales')
                    ->options(function () {
                        return Branch::pluck('name', 'id');
                    })
                    ->query(function (Builder $query, array $data) {
                        if (filled($data['value'])) {
                            return $query->whereHas('employee', function (Builder $query) use ($data) {
                                $query->where('branch_id', $data['value']);
                            });
                        }
                    })
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Todos los estados')
                    ->options(AttendanceDay::getStatusOptions())
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

                Filter::make('date')
                    ->label('Rango de Fechas')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->maxDate(now()),
                        DatePicker::make('to')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
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
            ->actions([
                self::getApproveOvertimeTableAction(),

                ViewAction::make(),

                self::getExportPdfTableAction(),

                self::getCalculateTableAction(),
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
                                ->withFilename('asistencias_' . now()->format('d_m_Y_H_i_s')),
                        ])
                        ->label('Exportar a Excel')
                        ->color('primary')
                        ->icon('heroicon-o-arrow-down-tray'),

                    BulkAction::make('approve_overtime')
                        ->label('Aprobar Horas Extra')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar horas extra seleccionadas')
                        ->modalDescription(function (Collection $records) {
                            $withExtraHours = $records->where('extra_hours', '>', 0);
                            $totalHours = $withExtraHours->sum('extra_hours');
                            $alreadyApproved = $withExtraHours->where('overtime_approved', true)->count();
                            $pending = $withExtraHours->where('overtime_approved', false)->count();

                            return "Total: {$withExtraHours->count()} registro(s) con {$totalHours} hrs extra. Aprobadas: {$alreadyApproved}, Pendientes: {$pending}.";
                        })
                        ->modalSubmitActionLabel('Aprobar')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $approved = 0;
                            $skipped = 0;

                            foreach ($records as $day) {
                                if ($day->extra_hours > 0 && !$day->overtime_approved) {
                                    $day->overtime_approved = true;
                                    $day->save();
                                    $approved++;
                                } else {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->title('¡Aprobación completada!')
                                ->body("Aprobadas: {$approved} | Omitidas (sin hrs extra o ya aprobadas): {$skipped}")
                                ->success()
                                ->send();
                        }),

                    BulkAction::make('revoke_overtime')
                        ->label('Revocar Horas Extra')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Revocar aprobación de horas extra')
                        ->modalDescription(function (Collection $records) {
                            $approved = $records->where('overtime_approved', true)->count();
                            $totalHours = $records->where('overtime_approved', true)->sum('extra_hours');

                            return "Se revocará la aprobación de {$approved} registro(s) con {$totalHours} hrs extra aprobadas.";
                        })
                        ->modalSubmitActionLabel('Revocar')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            $revoked = 0;
                            $skipped = 0;

                            foreach ($records as $day) {
                                if ($day->overtime_approved) {
                                    $day->overtime_approved = false;
                                    $day->save();
                                    $revoked++;
                                } else {
                                    $skipped++;
                                }
                            }

                            Notification::make()
                                ->title('¡Revocación completada!')
                                ->body("Revocadas: {$revoked} | Omitidas (ya sin aprobar): {$skipped}")
                                ->success()
                                ->send();
                        }),

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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Retorna la acción de aprobar/revocar horas extras para tabla
     */
    public static function getApproveOvertimeTableAction(): TableAction
    {
        return TableAction::make('approve_overtime')
            ->label(fn(AttendanceDay $record) => $record->overtime_approved ? 'Revocar' : 'Aprobar')
            ->icon(fn(AttendanceDay $record) => $record->overtime_approved ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
            ->color(fn(AttendanceDay $record) => $record->overtime_approved ? 'danger' : 'success')
            ->visible(fn(AttendanceDay $record) => $record->extra_hours > 0)
            ->tooltip(
                fn(AttendanceDay $record) => $record->overtime_approved
                    ? 'Revocar aprobación de ' . $record->extra_hours . ' hrs extra'
                    : 'Aprobar ' . $record->extra_hours . ' hrs extra'
            )
            ->requiresConfirmation()
            ->modalHeading(
                fn(AttendanceDay $record) => $record->overtime_approved
                    ? 'Revocar aprobación de horas extra'
                    : 'Aprobar horas extra'
            )
            ->modalDescription(
                fn(AttendanceDay $record) => $record->overtime_approved
                    ? "Se revocará la aprobación de {$record->extra_hours} hrs extra para {$record->employee->full_name} del {$record->date->format('d/m/Y')}."
                    : "Se aprobarán {$record->extra_hours} hrs extra para {$record->employee->full_name} del {$record->date->format('d/m/Y')}."
            )
            ->modalSubmitActionLabel(fn(AttendanceDay $record) => $record->overtime_approved ? 'Sí, revocar' : 'Sí, aprobar')
            ->action(function (AttendanceDay $record) {
                $wasApproved = $record->overtime_approved;
                $record->overtime_approved = !$wasApproved;
                $record->save();

                $action = $wasApproved ? 'revocada' : 'aprobada';

                Notification::make()
                    ->title("Aprobación {$action}")
                    ->body("Las {$record->extra_hours} hrs extra han sido {$action}s exitosamente.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Retorna la acción de aprobar/revocar horas extras para páginas (header actions)
     */
    public static function getApproveOvertimeAction(): Action
    {
        return Action::make('approve_overtime')
            ->label(fn(AttendanceDay $record) => $record->overtime_approved ? 'Revocar Horas Extra' : 'Aprobar Horas Extra')
            ->icon(fn(AttendanceDay $record) => $record->overtime_approved ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
            ->color(fn(AttendanceDay $record) => $record->overtime_approved ? 'danger' : 'success')
            ->visible(fn(AttendanceDay $record) => $record->extra_hours > 0)
            ->tooltip(
                fn(AttendanceDay $record) => $record->overtime_approved
                    ? 'Revocar aprobación de ' . $record->extra_hours . ' hrs extra'
                    : 'Aprobar ' . $record->extra_hours . ' hrs extra'
            )
            ->requiresConfirmation()
            ->modalHeading(
                fn(AttendanceDay $record) => $record->overtime_approved
                    ? 'Revocar aprobación de horas extra'
                    : 'Aprobar horas extra'
            )
            ->modalDescription(
                fn(AttendanceDay $record) => $record->overtime_approved
                    ? "Se revocará la aprobación de {$record->extra_hours} hrs extra para {$record->employee->full_name} del {$record->date->format('d/m/Y')}."
                    : "Se aprobarán {$record->extra_hours} hrs extra para {$record->employee->full_name} del {$record->date->format('d/m/Y')}."
            )
            ->modalSubmitActionLabel(fn(AttendanceDay $record) => $record->overtime_approved ? 'Sí, revocar' : 'Sí, aprobar')
            ->action(function (AttendanceDay $record) {
                $wasApproved = $record->overtime_approved;
                $record->overtime_approved = !$wasApproved;
                $record->save();

                $action = $wasApproved ? 'revocada' : 'aprobada';

                Notification::make()
                    ->title("Aprobación {$action}")
                    ->body("Las {$record->extra_hours} hrs extra han sido {$action}s exitosamente.")
                    ->success()
                    ->send();
            });
    }

    /**
     * Retorna la acción de exportar PDF para tabla
     */
    public static function getExportPdfTableAction(): TableAction
    {
        return TableAction::make('export')
            ->label('PDF')
            ->icon('heroicon-o-printer')
            ->color('info')
            ->tooltip('Exportar registro como PDF')
            ->url(fn(AttendanceDay $record) => route('attendance-days.export', ['attendance_day' => $record->id]))
            ->openUrlInNewTab();
    }

    /**
     * Retorna la acción de exportar PDF para páginas (header actions)
     */
    public static function getExportPdfAction(): Action
    {
        return Action::make('export')
            ->label('PDF')
            ->icon('heroicon-o-printer')
            ->color('info')
            ->tooltip('Exportar registro como PDF')
            ->url(fn(AttendanceDay $record) => route('attendance-days.export', ['attendance_day' => $record->id]))
            ->openUrlInNewTab();
    }

    /**
     * Retorna la acción de calcular/recalcular asistencia para tabla
     */
    public static function getCalculateTableAction(): TableAction
    {
        return TableAction::make('calculate')
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
                self::calculateAttendanceRecord($record);
            });
    }

    /**
     * Retorna la acción de calcular/recalcular asistencia para páginas (header actions)
     */
    public static function getCalculateAction(): Action
    {
        return Action::make('calculate')
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
                self::calculateAttendanceRecord($record);
            });
    }

    /**
     * Retorna la acción de calcular asistencias para el día de hoy
     */
    public static function getCalculateTodayAction(): Action
    {
        return Action::make('calculate_today')
            ->label('Calcular hoy')
            ->icon('heroicon-o-bolt')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Calcular asistencias de hoy')
            ->modalDescription('Se calcularán o recalcularán las asistencias del día de hoy.')
            ->action(function () {
                try {
                    $today = now();

                    // Contar registros antes
                    $totalBefore = AttendanceDay::whereDate('date', $today)
                        ->where('is_calculated', true)
                        ->count();

                    AttendanceCalculator::applyForDateRange($today, $today);

                    // Contar registros después
                    $totalAfter = AttendanceDay::whereDate('date', $today)
                        ->where('is_calculated', true)
                        ->count();

                    $total = AttendanceDay::whereDate('date', $today)->count();
                    $newCalculated = $totalAfter - $totalBefore;
                    $recalculated = $totalBefore;

                    Notification::make()
                        ->title('Cálculo del día completado')
                        ->body("Fecha: {$today->format('d/m/Y')} | Total: {$total} | Nuevos: {$newCalculated} | Recalculados: {$recalculated}")
                        ->success()
                        ->duration(8000)
                        ->send();
                } catch (\Exception $e) {
                    Log::error('Error calculando asistencias de hoy', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    Notification::make()
                        ->title('Error al calcular')
                        ->body('No se pudo calcular las asistencias de hoy. Revisa los logs para más detalles.')
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    /**
     * Retorna la acción de calcular asistencias por rango de fechas
     */
    public static function getCalculateRangeAction(): Action
    {
        return Action::make('calculate_range')
            ->label('Calcular Asistencia')
            ->icon('heroicon-o-calculator')
            ->color('success')
            ->form([
                DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->required()
                    ->maxDate(now())
                    ->default(now()->startOfMonth())
                    ->reactive(),

                DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->required()
                    ->maxDate(now())
                    ->default(now())
                    ->afterOrEqual('start_date')
                    ->minDate(fn(Get $get) => $get('start_date'))
                    ->reactive(),

                Placeholder::make('stats')
                    ->label('Resumen del rango')
                    ->content(function (Get $get) {
                        $start = $get('start_date');
                        $end = $get('end_date');

                        if (!$start || !$end) {
                            return 'Selecciona ambas fechas para ver estadísticas.';
                        }

                        $total = AttendanceDay::whereBetween('date', [$start, $end])->count();
                        $calculated = AttendanceDay::whereBetween('date', [$start, $end])
                            ->where('is_calculated', true)
                            ->count();
                        $notCalculated = $total - $calculated;

                        if ($total === 0) {
                            return 'No hay registros en este rango de fechas.';
                        }

                        return "Total: {$total} | Calculados: {$calculated} | Sin calcular: {$notCalculated}";
                    })
                    ->columnSpanFull(),
            ])
            ->requiresConfirmation()
            ->modalHeading('Calcular asistencias por rango de fechas')
            ->modalDescription('Esto calculará o recalculará las asistencias de los registros entre las fechas seleccionadas.')
            ->modalSubmitActionLabel('Calcular')
            ->modalWidth('lg')
            ->action(function (array $data) {
                try {
                    $startDate = Carbon::parse($data['start_date']);
                    $endDate = Carbon::parse($data['end_date']);

                    // Contar registros antes
                    $totalBefore = AttendanceDay::whereBetween('date', [
                        $startDate->toDateString(),
                        $endDate->toDateString()
                    ])->where('is_calculated', true)->count();

                    // Ejecutar cálculo
                    AttendanceCalculator::applyForDateRange($startDate, $endDate);

                    // Contar registros después
                    $totalAfter = AttendanceDay::whereBetween('date', [
                        $startDate->toDateString(),
                        $endDate->toDateString()
                    ])->where('is_calculated', true)->count();

                    $newCalculated = $totalAfter - $totalBefore;
                    $recalculated = $totalBefore;

                    Notification::make()
                        ->title('Cálculo completado exitosamente')
                        ->body("Período: {$startDate->format('d/m/Y')} al {$endDate->format('d/m/Y')} | Nuevos calculados: {$newCalculated} | Recalculados: {$recalculated}")
                        ->success()
                        ->duration(8000)
                        ->send();
                } catch (\Exception $e) {
                    Log::error('Error calculando asistencias en rango de fechas', [
                        'start_date' => $data['start_date'] ?? null,
                        'end_date' => $data['end_date'] ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    Notification::make()
                        ->title('Error al calcular asistencias')
                        ->body('No se pudo completar el cálculo. Verifica las fechas e intenta nuevamente.')
                        ->danger()
                        ->persistent()
                        ->send();
                }
            });
    }

    /**
     * Calcula un registro de asistencia y muestra notificación
     */
    private static function calculateAttendanceRecord(AttendanceDay $record): void
    {
        try {
            $wasCalculated = $record->is_calculated;

            AttendanceCalculator::apply($record);
            $record->save();

            $action = $wasCalculated ? 'recalculado' : 'calculado';
            $statusMessages = AttendanceDay::getCalculationStatusMessages($wasCalculated);
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
                            ->color(fn($state) => AttendanceDay::getStatusColor($state))
                            ->formatStateUsing(fn($state) => AttendanceDay::getStatusLabel($state))
                            ->icon(fn($state) => AttendanceDay::getStatusIcon($state)),

                        TextEntry::make('is_calculated')
                            ->label('Estado de Cálculo')
                            ->badge()
                            ->color(fn($state) => AttendanceDay::getBooleanColor($state, 'success', 'warning'))
                            ->formatStateUsing(fn($state) => $state ? 'Calculado' : 'Pendiente')
                            ->icon(fn($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock'),

                        TextEntry::make('calculated_at')
                            ->label('Último Cálculo')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Nunca calculado')
                            ->icon('heroicon-o-calendar-days')
                            ->hidden(fn($record) => !$record->is_calculated),

                        TextEntry::make('anomaly_flag')
                            ->label('Anomalía')
                            ->badge()
                            ->color(fn($state) => AttendanceDay::getBooleanColor($state, 'danger', 'success'))
                            ->formatStateUsing(fn($state) => AttendanceDay::formatBoolean($state))
                            ->icon(fn($state) => $state ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle'),

                        TextEntry::make('manual_adjustment')
                            ->label('Ajuste Manual')
                            ->badge()
                            ->color(fn($state) => AttendanceDay::getBooleanColor($state, 'info', 'gray'))
                            ->formatStateUsing(fn($state) => AttendanceDay::formatBoolean($state))
                            ->icon(fn($state) => $state ? 'heroicon-o-pencil-square' : null),
                    ])->columns(3),

                Fieldset::make('Empleado')
                    ->schema([
                        TextEntry::make('employee.ci')
                            ->label('Cédula de Identidad')
                            ->icon('heroicon-o-identification')
                            ->copyable()
                            ->copyMessage('CI copiado')
                            ->weight('bold'),

                        TextEntry::make('employee.full_name')
                            ->label('Nombre Completo')
                            ->getStateUsing(fn($record) => $record->employee ? "{$record->employee->first_name} {$record->employee->last_name}" : 'N/A')
                            ->icon('heroicon-o-user'),

                        TextEntry::make('employee.branch.name')
                            ->label('Sucursal')
                            ->icon('heroicon-o-building-office')
                            ->badge()
                            ->color('info'),

                        TextEntry::make('employee.position.name')
                            ->label('Cargo')
                            ->icon('heroicon-o-briefcase'),

                        TextEntry::make('employee.position.department.name')
                            ->label('Departamento')
                            ->icon('heroicon-o-building-office-2')
                            ->default('N/A'),
                    ])->columns(3),

                Fieldset::make('Condiciones Especiales')
                    ->schema([
                        TextEntry::make('is_extraordinary_work')
                            ->label('Trabajo Extraordinario')
                            ->badge()
                            ->color(fn($state) => AttendanceDay::getBooleanColor($state, 'warning'))
                            ->formatStateUsing(fn($state) => AttendanceDay::formatBoolean($state))
                            ->icon(fn($state) => $state ? 'heroicon-o-star' : null)
                            ->hidden(fn($record) => !$record->is_extraordinary_work),

                        TextEntry::make('is_weekend')
                            ->label('Fin de Semana')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(fn($state) => AttendanceDay::formatBoolean($state))
                            ->icon('heroicon-o-calendar')
                            ->hidden(fn($record) => !$record->is_weekend),

                        TextEntry::make('is_holiday')
                            ->label('Feriado')
                            ->badge()
                            ->color('info')
                            ->formatStateUsing(fn($state) => AttendanceDay::formatBoolean($state))
                            ->icon('heroicon-o-gift')
                            ->hidden(fn($record) => !$record->is_holiday),

                        TextEntry::make('on_vacation')
                            ->label('Vacaciones')
                            ->badge()
                            ->color('success')
                            ->formatStateUsing(fn($state) => AttendanceDay::formatBoolean($state))
                            ->icon('heroicon-o-sun')
                            ->hidden(fn($record) => !$record->on_vacation),

                        TextEntry::make('justified_absence')
                            ->label('Ausencia Justificada')
                            ->badge()
                            ->color('warning')
                            ->formatStateUsing(fn($state) => AttendanceDay::formatBoolean($state))
                            ->icon('heroicon-o-document-text')
                            ->hidden(fn($record) => !$record->justified_absence),

                        TextEntry::make('notes')
                            ->label('Notas')
                            ->columnSpanFull()
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->hidden(fn($record) => empty($record->notes)),
                    ])->columns(3)
                    ->hidden(fn($record) => !$record->is_extraordinary_work && !$record->is_weekend && !$record->is_holiday && !$record->on_vacation && !$record->justified_absence && empty($record->notes)),

                Fieldset::make('Tiempos de Entrada y Salida')
                    ->schema([
                        TextEntry::make('expected_check_in')
                            ->label('Entrada Esperada')
                            ->icon('heroicon-o-clock')
                            ->placeholder('No definida'),

                        TextEntry::make('check_in_time')
                            ->label('Entrada Marcada')
                            ->icon('heroicon-o-arrow-right-on-rectangle')
                            ->color(fn($record) => $record->late_minutes > 0 ? 'danger' : 'success')
                            ->weight(fn($record) => $record->late_minutes > 0 ? 'bold' : null)
                            ->placeholder('Sin marcar'),

                        TextEntry::make('late_minutes')
                            ->label('Retraso')
                            ->badge()
                            ->color(fn($state) => $state > 0 ? 'danger' : 'success')
                            ->formatStateUsing(fn($state) => $state > 0 ? "{$state} min tarde" : 'A tiempo')
                            ->icon(fn($state) => $state > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle'),

                        TextEntry::make('expected_check_out')
                            ->label('Salida Esperada')
                            ->icon('heroicon-o-clock')
                            ->placeholder('No definida'),

                        TextEntry::make('check_out_time')
                            ->label('Salida Marcada')
                            ->icon('heroicon-o-arrow-left-on-rectangle')
                            ->color(fn($record) => $record->early_leave_minutes > 0 ? 'warning' : 'success')
                            ->weight(fn($record) => $record->early_leave_minutes > 0 ? 'bold' : null)
                            ->placeholder('Sin marcar'),

                        TextEntry::make('early_leave_minutes')
                            ->label('Salida Anticipada')
                            ->badge()
                            ->color(fn($state) => $state > 0 ? 'warning' : 'success')
                            ->formatStateUsing(fn($state) => $state > 0 ? "{$state} min antes" : 'A tiempo')
                            ->icon(fn($state) => $state > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle'),
                    ])->columns(3)
                    ->hidden(fn($record) => $record->status !== 'present' && !$record->check_in_time && !$record->check_out_time),

                Fieldset::make('Resumen de Horas')
                    ->schema([
                        TextEntry::make('expected_hours')
                            ->label('Horas Esperadas')
                            ->icon('heroicon-o-clock')
                            ->suffix(' hrs')
                            ->placeholder('No definidas'),

                        TextEntry::make('total_hours')
                            ->label('Horas Trabajadas')
                            ->icon('heroicon-o-calculator')
                            ->suffix(' hrs')
                            ->weight('bold')
                            ->color('primary')
                            ->placeholder('0 hrs'),

                        TextEntry::make('net_hours')
                            ->label('Horas Netas')
                            ->icon('heroicon-o-check-badge')
                            ->suffix(' hrs')
                            ->weight('bold')
                            ->color('success')
                            ->placeholder('0 hrs'),

                        TextEntry::make('expected_break_minutes')
                            ->label('Descanso Esperado')
                            ->icon('heroicon-o-clock')
                            ->suffix(' min')
                            ->placeholder('No definido'),

                        TextEntry::make('break_minutes')
                            ->label('Descanso Tomado')
                            ->icon('heroicon-o-pause-circle')
                            ->suffix(' min')
                            ->color(fn($record) => $record->break_minutes > ($record->expected_break_minutes ?? 0) ? 'warning' : null)
                            ->weight(fn($record) => $record->break_minutes > ($record->expected_break_minutes ?? 0) ? 'bold' : null)
                            ->placeholder('0 min'),

                        TextEntry::make('extra_hours')
                            ->label('Horas Extra')
                            ->badge()
                            ->icon('heroicon-o-star')
                            ->color(fn($state) => $state > 0 ? 'warning' : 'gray')
                            ->suffix(' hrs')
                            ->placeholder('0')
                            ->hidden(fn($record) => $record->extra_hours <= 0 && !$record->overtime_approved),

                        TextEntry::make('overtime_approved')
                            ->label('Aprobación Horas Extra')
                            ->badge()
                            ->color(fn($state) => AttendanceDay::getBooleanColor($state, 'success', 'danger'))
                            ->formatStateUsing(fn($state) => $state ? 'Aprobadas' : 'Pendientes')
                            ->icon(fn($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                            ->hidden(fn($record) => $record->extra_hours <= 0),
                    ])->columns(3)
                    ->hidden(fn($record) => $record->status !== 'present'),
            ]);
    }
}
