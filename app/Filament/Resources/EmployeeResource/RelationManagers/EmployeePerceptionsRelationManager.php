<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeePerception;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
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
use Illuminate\Support\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Gestiona las percepciones asignadas al empleado desde su vista de detalle. */
class EmployeePerceptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'employeePerceptions';
    protected static ?string $title = 'Percepciones';
    protected static ?string $modelLabel = 'Percepción';
    protected static ?string $pluralModelLabel = 'Percepciones';

    /**
     * Determina si la relación debe ser de solo lectura.
     *
     * @return boolean
     */
    public function isReadonly(): bool
    {
        return is_a($this->getPageClass(), ViewRecord::class, true);
    }

    /**
     * Define el formulario para crear/editar asignaciones de percepciones a empleados.
     *
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('perception_id')
                    ->label('Percepción')
                    ->relationship(
                        name: 'perception',
                        modifyQueryUsing: fn(Builder $query) => $query->where('is_active', true)->orderBy('name')
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn(Model $record) =>
                        $record->name . ' (' .
                            ($record->isFixed()
                                ? '₲ ' . number_format($record->amount, 0, ',', '.')
                                : $record->percent . '%'
                            ) . ')'
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn(Set $set) => $set('custom_amount', null))
                    ->hiddenOn('edit')
                    ->helperText('Solo se pueden asignar percepciones que estén activas en el sistema.'),

                DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->native(false)
                    ->default(now())
                    ->required()
                    ->live()
                    ->closeOnDateSelection()
                    ->displayFormat('d/m/Y')
                    ->maxDate(fn(Get $get) => $get('end_date') ?: null)
                    ->helperText('La fecha de inicio no puede ser posterior a la fecha de fin'),

                DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->native(false)
                    ->after('start_date')
                    ->live()
                    ->closeOnDateSelection()
                    ->displayFormat('d/m/Y')
                    ->minDate(fn(Get $get) => $get('start_date') ?: null)
                    ->helperText('Dejar vacío si la percepción no tiene fecha de fin'),

                TextInput::make('custom_amount')
                    ->label('Monto personalizado')
                    ->numeric()
                    ->prefix('₲')
                    ->minValue(1)
                    ->maxValue(999999999)
                    ->step(1)
                    ->helperText('Dejar vacío para usar el monto configurado en la percepción')
                    ->columnSpan(fn(string $operation) => $operation === 'edit' ? 2 : 1),

                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(1)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Define la tabla para listar las percepciones asignadas a un empleado.
     *
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with('perception'))
            ->columns([
                TextColumn::make('perception.code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->tooltip('Copiar código al portapapeles')
                    ->copyMessage('Código copiado al portapapeles')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('perception.name')
                    ->label('Percepción')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                TextColumn::make('perception.calculation')
                    ->label('Tipo')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'fixed' => 'Fijo',
                        'percentage' => 'Porcentaje',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'fixed' => 'primary',
                        'percentage' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('amount_display')
                    ->label('Monto')
                    ->getStateUsing(function ($record) {
                        if ($record->custom_amount !== null) {
                            return '₲ ' . number_format($record->custom_amount, 0, ',', '.');
                        } elseif ($record->perception->isPercentage()) {
                            return $record->perception->percent . '%';
                        }
                        return '₲ ' . number_format($record->perception->amount, 0, ',', '.');
                    })
                    ->badge()
                    ->color(fn($record) => $record->custom_amount !== null ? 'warning' : 'gray'),

                TextColumn::make('start_date')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color('success'),

                TextColumn::make('end_date')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color('danger')
                    ->placeholder('Activo'),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
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
                    ->native(false)
                    ->query(fn(Builder $query, array $data) => match ($data['value'] ?? null) {
                        'active'   => $query->active(),
                        'pending'  => $query->pending(),
                        'inactive' => $query->inactive(),
                        default    => $query,
                    }),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar percepción')
                    ->icon('heroicon-o-plus')
                    ->before(function (array $data, CreateAction $action) {
                        $hasActive = EmployeePerception::where('employee_id', $this->getOwnerRecord()->id)
                            ->where('perception_id', $data['perception_id'])
                            ->where('start_date', '<=', now())
                            ->where(fn($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
                            ->exists();

                        if ($hasActive) {
                            Notification::make()
                                ->danger()
                                ->title('Asignación duplicada')
                                ->body('Este empleado ya tiene una asignación activa para esta percepción.')
                                ->send();

                            $action->halt();
                        }
                    })
                    ->successNotificationTitle('Percepción asignada exitosamente'),
            ])
            ->actions([
                Action::make('deactivate')
                    ->label('Remover')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn($record) => !$this->isReadonly() && $record->isActive())
                    ->modalHeading('Remover Percepción del Empleado')
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
                                ->title('Percepción removida exitosamente')
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
                    ->modalHeading('Reactivar Percepción')
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
                                ->title('Percepción reactivada exitosamente')
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
                        ->modalHeading('Editar Asignación')
                        ->color('primary')
                        ->successNotificationTitle('Asignación actualizada exitosamente'),

                    DeleteAction::make()
                        ->label('Borrar')
                        ->modalHeading('Borrar Asignación')
                        ->modalDescription('¿Está seguro de que desea borrar permanentemente esta asignación del historial?')
                        ->successNotificationTitle('Asignación borrada del historial'),
                ]),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('deactivate')
                        ->label('Remover seleccionadas')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->modalHeading('Remover Percepciones del Empleado')
                        ->modalDescription('Confirmá la fecha hasta la cual aplican estas percepciones para el empleado.')
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
                            $endDate = Carbon::parse($data['end_date']);
                            $removed = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if ($record->isActive()) {
                                    $record->deactivate($endDate) ? $removed++ : null;
                                } else {
                                    $skipped++;
                                }
                            }

                            $body = "{$removed} percepción(es) removida(s) con fecha de fin {$endDate->format('d/m/Y')}.";
                            if ($skipped > 0) {
                                $body .= " {$skipped} omitida(s) por no estar activa(s).";
                            }

                            Notification::make()
                                ->success()
                                ->title('Operación completada')
                                ->body($body)
                                ->send();
                        }),

                    BulkAction::make('reactivate')
                        ->label('Reactivar seleccionadas')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->modalHeading('Reactivar Percepciones')
                        ->modalDescription('Confirmá la fecha desde la cual se reactivan estas percepciones para el empleado.')
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

                            $body = "{$reactivated} percepción(es) reactivada(s) desde {$startDate->format('d/m/Y')}.";
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
                        ->label('Borrar del historial')
                        ->modalHeading('Borrar Asignaciones')
                        ->modalDescription('¿Está seguro de que desea borrar permanentemente estas asignaciones del historial?')
                        ->successNotificationTitle('Asignaciones borradas del historial'),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No hay percepciones')
            ->emptyStateDescription('Comienza agregando una percepción al empleado')
            ->emptyStateIcon('heroicon-o-plus-circle');
    }
}
