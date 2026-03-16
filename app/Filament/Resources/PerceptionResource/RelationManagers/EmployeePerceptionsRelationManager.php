<?php

namespace App\Filament\Resources\PerceptionResource\RelationManagers;

use App\Models\EmployeePerception;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class EmployeePerceptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'employeePerceptions';
    protected static ?string $title = 'Empleados Asignados';
    protected static ?string $modelLabel = 'asignación';
    protected static ?string $pluralModelLabel = 'asignaciones';

    /**
     * Determina si la relación debe ser de solo lectura (sin acciones de edición o eliminación) dependiendo de la página actual.
     *
     * @return boolean
     */
    public function isReadonly(): bool
    {
        return is_a($this->getPageClass(), ViewRecord::class, true);
    }

    /**
     * Define el formulario para asignar empleados a la percepción.
     *
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->label('Empleado')
                    ->relationship(
                        'employee',
                        'first_name',
                        fn(Builder $query) => $query
                            ->where('status', 'active')
                            ->whereDoesntHave('employeePerceptions', function ($q) {
                                $q->where('perception_id', $this->getOwnerRecord()->id)
                                    ->where('start_date', '<=', now())
                                    ->where(fn($q2) => $q2->whereNull('end_date')->orWhere('end_date', '>=', now()));
                            })
                    )
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->first_name . ' ' . $record->last_name . ' (' . $record->ci . ')')
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->required()
                    ->native(false)
                    ->helperText('Solo se muestran empleados activos sin esta percepción.')
                    ->hiddenOn('edit'),

                DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->required()
                    ->default(now())
                    ->native(false)
                    ->live()
                    ->closeOnDateSelection()
                    ->displayFormat('d/m/Y')
                    ->maxDate(fn(Get $get) => $get('end_date') ?: null)
                    ->helperText('La percepción se considera activa desde esta fecha.'),

                DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->native(false)
                    ->live()
                    ->closeOnDateSelection()
                    ->displayFormat('d/m/Y')
                    ->after('start_date')
                    ->minDate(fn(Get $get) => $get('start_date') ?: null)
                    ->helperText('Dejar vacío si la percepción no tiene fecha de fin definida.'),

                TextInput::make('custom_amount')
                    ->label('Monto Personalizado')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(999999999)
                    ->step(1)
                    ->prefix('₲')
                    ->helperText('Establece un monto personalizado para este empleado. Dejar vacío para usar el monto por defecto de la percepción.'),

                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(1)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Define la tabla para mostrar los empleados asignados a la percepción.
     *
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('employee.first_name')
            ->modifyQueryUsing(fn(Builder $query) => $query->with('employee.activeContract.position'))
            ->defaultSort('start_date', 'desc')
            ->columns([
                ImageColumn::make('employee.photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn($record) => $record->employee->avatar_url)
                    ->toggleable(),

                TextColumn::make('employee.full_name')
                    ->label('Nombre Completo')
                    ->searchable(['employee.first_name', 'employee.last_name'])
                    ->sortable(['employee.first_name', 'employee.last_name'])
                    ->wrap(),

                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->icon('heroicon-o-identification')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->tooltip('Haz clic para copiar')
                    ->copyMessage('CI copiado al portapapeles')
                    ->toggleable(),

                TextColumn::make('employee.activeContract.position.name')
                    ->label('Cargo')
                    ->icon('heroicon-o-briefcase')
                    ->badge()
                    ->color('primary')
                    ->default('-')
                    ->toggleable(),

                TextColumn::make('start_date')
                    ->label('Fecha Inicio')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('end_date')
                    ->label('Fecha Fin')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color('danger')
                    ->placeholder('Activo')
                    ->toggleable(),

                TextColumn::make('custom_amount')
                    ->label('Monto Personalizado')
                    ->money('PYG', locale: 'es_PY')
                    ->placeholder('Monto por defecto')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->options([
                        'active'   => 'Activo',
                        'pending'  => 'Pendiente',
                        'inactive' => 'Inactivo',
                    ])
                    ->query(fn(Builder $query, array $data) => match ($data['value'] ?? null) {
                        'active'   => $query->active(),
                        'pending'  => $query->pending(),
                        'inactive' => $query->inactive(),
                        default    => $query,
                    })
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Asignar Empleado')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('Asignar Empleado a la Percepción')
                    ->before(function (array $data, CreateAction $action) {
                        $hasActive = EmployeePerception::where('employee_id', $data['employee_id'])
                            ->where('perception_id', $this->getOwnerRecord()->id)
                            ->where('start_date', '<=', now())
                            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
                            ->exists();

                        if ($hasActive) {
                            Notification::make()
                                ->danger()
                                ->title('Ya existe una asignación activa')
                                ->body('Este empleado ya tiene esta percepción activa en otro registro.')
                                ->send();

                            $action->halt();
                        }
                    })
                    ->successNotificationTitle('Empleado asignado exitosamente'),
            ])
            ->actions([
                Action::make('deactivate')
                    ->label('Remover')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn($record) => !$this->isReadonly() && $record->isActive())
                    ->modalHeading('Remover Empleado de la Percepción')
                    ->modalDescription('Confirmá la fecha hasta la cual aplica esta percepción para el empleado.')
                    ->modalSubmitActionLabel('Sí, remover')
                    ->form([
                        DatePicker::make('end_date')
                            ->label('Fecha de Fin')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->closeOnDateSelection()
                            ->displayFormat('d/m/Y'),
                    ])
                    ->action(function (EmployeePerception $record, array $data) {
                        $endDate = Carbon::parse($data['end_date']);

                        if ($record->deactivate($endDate)) {
                            Notification::make()
                                ->success()
                                ->title('Empleado removido exitosamente')
                                ->body("La percepción fue removida con fecha de fin {$endDate->format('d/m/Y')}.")
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Error al remover')
                                ->body('No se pudo remover la asignación. Intente nuevamente.')
                                ->send();
                        }
                    }),

                Action::make('reactivate')
                    ->label('Reactivar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => !$this->isReadonly() && !$record->isActive())
                    ->modalHeading('Reactivar Asignación')
                    ->modalDescription('Confirmá la fecha desde la cual se reactiva esta percepción para el empleado.')
                    ->modalSubmitActionLabel('Sí, reactivar')
                    ->form([
                        DatePicker::make('start_date')
                            ->label('Fecha de Inicio')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->closeOnDateSelection()
                            ->displayFormat('d/m/Y'),

                        DatePicker::make('end_date')
                            ->label('Fecha de Fin')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->displayFormat('d/m/Y')
                            ->after('start_date')
                            ->helperText('Opcional: dejar vacío si la percepción no tiene fecha de fin definida'),
                    ])
                    ->action(function (EmployeePerception $record, array $data) {
                        $hasActive = EmployeePerception::where('employee_id', $record->employee_id)
                            ->where('perception_id', $record->perception_id)
                            ->where('id', '!=', $record->id)
                            ->where('start_date', '<=', now())
                            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
                            ->exists();

                        if ($hasActive) {
                            Notification::make()
                                ->danger()
                                ->title('No se puede reactivar')
                                ->body('Este empleado ya tiene una asignación activa para esta percepción.')
                                ->send();
                            return;
                        }

                        $startDate = Carbon::parse($data['start_date']);
                        $endDate   = filled($data['end_date']) ? Carbon::parse($data['end_date']) : null;

                        if ($record->reactivate($startDate, $endDate)) {
                            $body = $endDate
                                ? "Reactivada desde {$startDate->format('d/m/Y')} hasta {$endDate->format('d/m/Y')}."
                                : "Reactivada desde {$startDate->format('d/m/Y')} sin fecha de fin.";

                            Notification::make()
                                ->success()
                                ->title('Asignación reactivada exitosamente')
                                ->body($body)
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Error al reactivar')
                                ->body('No se pudo reactivar la asignación. Intente nuevamente.')
                                ->send();
                        }
                    }),

                ActionGroup::make([
                    EditAction::make()
                        ->color('primary')
                        ->modalHeading('Editar Asignación')
                        ->successNotificationTitle('Asignación actualizada exitosamente'),

                    DeleteAction::make()
                        ->label('Borrar')
                        ->modalHeading('Borrar Asignación')
                        ->modalDescription('¿Está seguro de que desea borrar permanentemente esta asignación del historial?')
                        ->successNotificationTitle('Asignación eliminada del historial'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('deactivate')
                        ->label('Remover seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->modalHeading('Remover Empleados de la Percepción')
                        ->modalDescription('Confirmá la fecha hasta la cual aplica esta percepción para los empleados seleccionados.')
                        ->modalSubmitActionLabel('Sí, remover')
                        ->form([
                            DatePicker::make('end_date')
                                ->label('Fecha de Fin')
                                ->required()
                                ->default(now())
                                ->native(false)
                                ->closeOnDateSelection()
                                ->displayFormat('d/m/Y'),
                        ])
                        ->action(function ($records, array $data) {
                            $endDate  = Carbon::parse($data['end_date']);
                            $removed  = 0;
                            $skipped  = 0;

                            foreach ($records as $record) {
                                if ($record->isActive()) {
                                    $record->deactivate($endDate) ? $removed++ : null;
                                } else {
                                    $skipped++;
                                }
                            }

                            $body = "{$removed} empleado(s) removido(s) con fecha de fin {$endDate->format('d/m/Y')}.";
                            if ($skipped > 0) {
                                $body .= " {$skipped} omitido(s) por no estar activo(s).";
                            }

                            Notification::make()
                                ->success()
                                ->title('Operación completada')
                                ->body($body)
                                ->send();
                        }),

                    BulkAction::make('reactivate')
                        ->label('Reactivar seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->modalHeading('Reactivar Asignaciones')
                        ->modalDescription('Confirmá la fecha desde la cual se reactivan las asignaciones seleccionadas.')
                        ->modalSubmitActionLabel('Sí, reactivar')
                        ->form([
                            DatePicker::make('start_date')
                                ->label('Fecha de Inicio')
                                ->required()
                                ->default(now())
                                ->native(false)
                                ->closeOnDateSelection()
                                ->displayFormat('d/m/Y'),

                            DatePicker::make('end_date')
                                ->label('Fecha de Fin')
                                ->native(false)
                                ->closeOnDateSelection()
                                ->displayFormat('d/m/Y')
                                ->after('start_date')
                                ->helperText('Opcional: dejar vacío si no tiene fecha de fin definida'),
                        ])
                        ->action(function ($records, array $data) {
                            $startDate   = Carbon::parse($data['start_date']);
                            $endDate     = filled($data['end_date']) ? Carbon::parse($data['end_date']) : null;
                            $reactivated = 0;
                            $skipped     = 0;

                            foreach ($records as $record) {
                                if ($record->isActive()) {
                                    $skipped++;
                                    continue;
                                }

                                $hasActive = EmployeePerception::where('employee_id', $record->employee_id)
                                    ->where('perception_id', $record->perception_id)
                                    ->where('id', '!=', $record->id)
                                    ->where('start_date', '<=', now())
                                    ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
                                    ->exists();

                                if ($hasActive) {
                                    $skipped++;
                                    continue;
                                }

                                if ($record->reactivate($startDate, $endDate)) {
                                    $reactivated++;
                                }
                            }

                            $body = "{$reactivated} asignación(es) reactivada(s) desde {$startDate->format('d/m/Y')}.";
                            if ($skipped > 0) {
                                $body .= " {$skipped} omitida(s) por ya estar activa(s) o tener conflicto.";
                            }

                            Notification::make()
                                ->success()
                                ->title('Operación completada')
                                ->body($body)
                                ->send();
                        }),

                    DeleteBulkAction::make()
                        ->label('Borrar seleccionados')
                        ->modalHeading('Borrar Asignaciones')
                        ->modalDescription('¿Está seguro de que desea borrar permanentemente estas asignaciones del historial?')
                        ->successNotificationTitle('Asignaciones borradas del historial'),
                ]),
            ])
            ->emptyStateHeading('No hay empleados asignados')
            ->emptyStateDescription('Comience asignando empleados a esta percepción.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
