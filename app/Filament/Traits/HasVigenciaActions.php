<?php

namespace App\Filament\Traits;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Proporciona acciones, columnas y filtros comunes para RelationManagers que gestionan
 * asignaciones con vigencia por fechas (start_date / end_date).
 *
 * Implementaciones: EmployeePerceptionsRelationManager, EmployeeDeductionsRelationManager.
 */
trait HasVigenciaActions
{
    /** Nombre del campo FK de la entidad asignada (ej. 'perception_id'). */
    abstract protected function getEntityField(): string;

    /** Nombre singular de la entidad para mensajes (ej. 'Percepción'). */
    abstract protected function getEntityName(): string;

    /** FQCN del modelo de asignación (ej. EmployeePerception::class). */
    abstract protected function getEntityModelClass(): string;

    /**
     * Campos comunes del formulario: start_date, end_date, custom_amount, notes.
     * El RM debe anteponer el Select de la entidad antes de estos campos.
     *
     * @return array<int, mixed>
     */
    protected function vigenciaFormSchema(): array
    {
        $entityName = $this->getEntityName();

        return [
            DatePicker::make('start_date')
                ->label('Fecha de inicio')
                ->native(false)
                ->default(now())
                ->required()
                ->live()
                ->closeOnDateSelection()
                ->displayFormat('d/m/Y')
                ->maxDate(fn (\Filament\Forms\Get $get) => $get('end_date') ?: null)
                ->helperText('La fecha de inicio no puede ser posterior a la fecha de fin'),

            DatePicker::make('end_date')
                ->label('Fecha de fin')
                ->native(false)
                ->after('start_date')
                ->live()
                ->closeOnDateSelection()
                ->displayFormat('d/m/Y')
                ->minDate(fn (\Filament\Forms\Get $get) => $get('start_date') ?: null)
                ->helperText('Dejar vacío si la asignación no tiene fecha de fin'),

            TextInput::make('custom_amount')
                ->label('Monto personalizado')
                ->numeric()
                ->prefix('₲')
                ->minValue(1)
                ->maxValue(999999999)
                ->step(1)
                ->helperText('Dejar vacío para usar el monto configurado en la '.mb_strtolower($entityName))
                ->columnSpan(fn (string $operation) => $operation === 'edit' ? 2 : 1),

            Textarea::make('notes')
                ->label('Notas')
                ->rows(1)
                ->maxLength(500)
                ->columnSpanFull(),
        ];
    }

    /**
     * Columna "Tipo" (cálculo: fijo vs. porcentaje), compartida entre ambos RMs.
     */
    protected function vigenciaCalculationColumn(): TextColumn
    {
        $relation = Str::beforeLast($this->getEntityField(), '_id');

        return TextColumn::make("{$relation}.calculation")
            ->label('Tipo')
            ->formatStateUsing(fn (string $state): string => match ($state) {
                'fixed' => 'Fijo',
                'percentage' => 'Porcentaje',
                default => $state,
            })
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'fixed' => 'primary',
                default => 'gray',
            })
            ->sortable();
    }

    /**
     * Columna "Monto" que muestra el monto personalizado o el definido en la entidad.
     */
    protected function vigenciaAmountColumn(): TextColumn
    {
        $relation = Str::beforeLast($this->getEntityField(), '_id');

        return TextColumn::make('amount_display')
            ->label('Monto')
            ->getStateUsing(function ($record) use ($relation) {
                if ($record->custom_amount !== null) {
                    return '₲ '.number_format($record->custom_amount, 0, ',', '.');
                }
                if ($record->$relation === null) {
                    return '-';
                }
                if ($record->$relation->isPercentage()) {
                    return $record->$relation->percent.'%';
                }

                return '₲ '.number_format($record->$relation->amount, 0, ',', '.');
            })
            ->badge()
            ->color(fn ($record) => $record->custom_amount !== null ? 'warning' : 'gray');
    }

    /**
     * Columnas de fechas y auditoría comunes: Desde, Hasta, Creado, Actualizado.
     *
     * @return array<int, TextColumn>
     */
    protected function vigenciaDateColumns(): array
    {
        return [
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
        ];
    }

    /**
     * Filtro de estado (activo / pendiente / inactivo) usando scopes del modelo.
     */
    protected function vigenciaEstadoFilter(): SelectFilter
    {
        return SelectFilter::make('estado')
            ->label('Estado')
            ->placeholder('Todos')
            ->options([
                'active' => 'Activo',
                'pending' => 'Pendiente',
                'inactive' => 'Inactivo',
            ])
            ->native(false)
            ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                'active' => $query->active(),
                'pending' => $query->pending(),
                'inactive' => $query->inactive(),
                default => $query,
            });
    }

    /**
     * Acción de fila para remover una asignación activa estableciendo su end_date.
     */
    protected function vigenciaDeactivateAction(): Action
    {
        $entityName = $this->getEntityName();
        $entityNameLower = mb_strtolower($entityName);

        return Action::make('deactivate')
            ->label('Remover')
            ->icon('heroicon-o-x-circle')
            ->color('warning')
            ->visible(fn ($record) => ! $this->isReadonly() && $record->isActive())
            ->modalHeading('Remover '.$entityName.' del Empleado')
            ->modalDescription('Confirmá la fecha hasta la cual aplica esta '.$entityNameLower.' para el empleado.')
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
            ->action(function ($record, array $data) use ($entityName, $entityNameLower) {
                $endDate = Carbon::parse($data['end_date']);

                if ($endDate->lt($record->start_date)) {
                    Notification::make()
                        ->danger()
                        ->title('Fecha inválida')
                        ->body('La fecha de fin no puede ser anterior a la fecha de inicio ('.$record->start_date->format('d/m/Y').').')
                        ->send();

                    return;
                }

                if ($record->deactivate($endDate)) {
                    Notification::make()
                        ->success()
                        ->title($entityName.' removida exitosamente')
                        ->body('La '.$entityNameLower.' fue removida con fecha de fin '.$endDate->format('d/m/Y').'.')
                        ->send();
                } else {
                    Notification::make()
                        ->danger()
                        ->title('Error al remover')
                        ->body('No se pudo remover la asignación. Intente nuevamente.')
                        ->send();
                }
            });
    }

    /**
     * Acción de fila para reactivar una asignación inactiva con nueva start_date.
     */
    protected function vigenciaReactivateAction(): Action
    {
        $entityField = $this->getEntityField();
        $entityName = $this->getEntityName();
        $entityNameLower = mb_strtolower($entityName);
        $entityModelClass = $this->getEntityModelClass();

        return Action::make('reactivate')
            ->label('Reactivar')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn ($record) => ! $this->isReadonly() && ! $record->isActive())
            ->modalHeading('Reactivar '.$entityName)
            ->modalDescription('Confirmá la fecha desde la cual se reactiva esta '.$entityNameLower.' para el empleado.')
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
            ->action(function ($record, array $data) use ($entityField, $entityName, $entityNameLower, $entityModelClass) {
                $hasActive = $entityModelClass::where('employee_id', $record->employee_id)
                    ->where($entityField, $record->$entityField)
                    ->where('id', '!=', $record->id)
                    ->where('start_date', '<=', now())
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
                    ->exists();

                if ($hasActive) {
                    Notification::make()
                        ->danger()
                        ->title('No se puede reactivar')
                        ->body('Este empleado ya tiene una asignación activa para esta '.$entityNameLower.'.')
                        ->send();

                    return;
                }

                $startDate = Carbon::parse($data['start_date']);

                $hasSameStartDate = $entityModelClass::where('employee_id', $record->employee_id)
                    ->where($entityField, $record->$entityField)
                    ->where('id', '!=', $record->id)
                    ->where('start_date', $startDate)
                    ->exists();

                if ($hasSameStartDate) {
                    Notification::make()
                        ->danger()
                        ->title('Fecha de inicio duplicada')
                        ->body('Ya existe otro registro con esa fecha de inicio para esta '.$entityNameLower.'. Elija otra fecha.')
                        ->send();

                    return;
                }

                $endDate = filled($data['end_date']) ? Carbon::parse($data['end_date']) : null;

                if ($record->reactivate($startDate, $endDate)) {
                    $body = $endDate
                        ? 'Reactivada desde '.$startDate->format('d/m/Y').' hasta '.$endDate->format('d/m/Y').'.'
                        : 'Reactivada desde '.$startDate->format('d/m/Y').' sin fecha de fin.';

                    Notification::make()
                        ->success()
                        ->title($entityName.' reactivada exitosamente')
                        ->body($body)
                        ->send();
                } else {
                    Notification::make()
                        ->danger()
                        ->title('Error al reactivar')
                        ->body('No se pudo reactivar la asignación. Intente nuevamente.')
                        ->send();
                }
            });
    }

    /**
     * Closure para el ->before() del CreateAction: valida duplicados activos y start_date duplicada.
     */
    protected function vigenciaCreateBefore(): \Closure
    {
        $entityField = $this->getEntityField();
        $entityNameLower = mb_strtolower($this->getEntityName());
        $entityModelClass = $this->getEntityModelClass();
        $employeeId = $this->getOwnerRecord()->id;

        return function (array $data, CreateAction $action) use ($entityField, $entityNameLower, $entityModelClass, $employeeId) {
            $hasActive = $entityModelClass::where('employee_id', $employeeId)
                ->where($entityField, $data[$entityField])
                ->where('start_date', '<=', now())
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
                ->exists();

            if ($hasActive) {
                Notification::make()
                    ->danger()
                    ->title('Asignación duplicada')
                    ->body('Este empleado ya tiene una asignación activa para esta '.$entityNameLower.'.')
                    ->send();

                $action->halt();

                return;
            }

            $hasSameStartDate = $entityModelClass::where('employee_id', $employeeId)
                ->where($entityField, $data[$entityField])
                ->where('start_date', $data['start_date'])
                ->exists();

            if ($hasSameStartDate) {
                Notification::make()
                    ->danger()
                    ->title('Fecha de inicio duplicada')
                    ->body('Ya existe un registro con esa fecha de inicio para esta '.$entityNameLower.'. Elija otra fecha.')
                    ->send();

                $action->halt();
            }
        };
    }

    /**
     * Closure para el ->before() del EditAction: valida start_date duplicada al editar.
     */
    protected function vigenciaEditBefore(): \Closure
    {
        $entityField = $this->getEntityField();
        $entityNameLower = mb_strtolower($this->getEntityName());
        $entityModelClass = $this->getEntityModelClass();

        return function ($record, array $data, EditAction $action) use ($entityField, $entityNameLower, $entityModelClass) {
            $hasSameStartDate = $entityModelClass::where('employee_id', $record->employee_id)
                ->where($entityField, $record->$entityField)
                ->where('id', '!=', $record->id)
                ->where('start_date', $data['start_date'])
                ->exists();

            if ($hasSameStartDate) {
                Notification::make()
                    ->danger()
                    ->title('Fecha de inicio duplicada')
                    ->body('Ya existe otro registro con esa fecha de inicio para esta '.$entityNameLower.'. Elija otra fecha.')
                    ->send();

                $action->halt();
            }
        };
    }

    /**
     * Bulk action para remover múltiples asignaciones activas estableciendo su end_date.
     */
    protected function vigenciaDeactivateBulkAction(): BulkAction
    {
        $entityName = $this->getEntityName();

        return BulkAction::make('deactivate')
            ->label('Remover seleccionadas')
            ->icon('heroicon-o-x-circle')
            ->color('warning')
            ->modalHeading('Remover asignaciones del Empleado')
            ->modalDescription('Confirmá la fecha hasta la cual aplican estas asignaciones para el empleado.')
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

                $body = "{$removed} asignación(es) removida(s) con fecha de fin ".$endDate->format('d/m/Y').'.';
                if ($skipped > 0) {
                    $body .= " {$skipped} omitida(s) por no estar activa(s).";
                }

                Notification::make()
                    ->success()
                    ->title('Operación completada')
                    ->body($body)
                    ->send();
            });
    }

    /**
     * Bulk action para reactivar múltiples asignaciones inactivas con nueva start_date.
     */
    protected function vigenciaReactivateBulkAction(): BulkAction
    {
        $entityField = $this->getEntityField();
        $entityModelClass = $this->getEntityModelClass();

        return BulkAction::make('reactivate')
            ->label('Reactivar seleccionadas')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->modalHeading('Reactivar Asignaciones')
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
            ->action(function ($records, array $data) use ($entityField, $entityModelClass) {
                $startDate = Carbon::parse($data['start_date']);
                $endDate = filled($data['end_date']) ? Carbon::parse($data['end_date']) : null;
                $reactivated = 0;
                $skipped = 0;

                foreach ($records as $record) {
                    if ($record->isActive()) {
                        $skipped++;

                        continue;
                    }

                    $hasActive = $entityModelClass::where('employee_id', $record->employee_id)
                        ->where($entityField, $record->$entityField)
                        ->where('id', '!=', $record->id)
                        ->where('start_date', '<=', now())
                        ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()))
                        ->exists();

                    if ($hasActive) {
                        $skipped++;

                        continue;
                    }

                    $hasSameStartDate = $entityModelClass::where('employee_id', $record->employee_id)
                        ->where($entityField, $record->$entityField)
                        ->where('id', '!=', $record->id)
                        ->where('start_date', $startDate)
                        ->exists();

                    if ($hasSameStartDate) {
                        $skipped++;

                        continue;
                    }

                    if ($record->reactivate($startDate, $endDate)) {
                        $reactivated++;
                    }
                }

                $body = "{$reactivated} asignación(es) reactivada(s) desde ".$startDate->format('d/m/Y').'.';
                if ($skipped > 0) {
                    $body .= " {$skipped} omitida(s) por ya estar activa(s) o tener conflicto.";
                }

                Notification::make()
                    ->success()
                    ->title('Operación completada')
                    ->body($body)
                    ->send();
            });
    }
}
