<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeeScheduleAssignment;
use App\Models\Schedule;
use App\Services\ScheduleAssignmentService;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * RelationManager para gestionar el historial de asignaciones de horario de un empleado.
 * Permite crear nuevas asignaciones (con validación de solapamiento) y consultar el historial.
 */
class ScheduleAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'scheduleAssignments';

    protected static ?string $title = 'Historial de Horarios';

    protected static ?string $modelLabel = 'Asignación';

    protected static ?string $pluralModelLabel = 'Asignaciones';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Define el formulario de asignación de horario.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('schedule_id')
                    ->label('Horario')
                    ->options(Schedule::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->columnSpanFull(),

                DatePicker::make('valid_from')
                    ->label('Vigente desde')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(today())
                    ->required()
                    ->closeOnDateSelection(),

                DatePicker::make('valid_until')
                    ->label('Vigente hasta')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->closeOnDateSelection()
                    ->after('valid_from')
                    ->helperText('Dejar vacío si el horario es indefinido.'),

                Textarea::make('notes')
                    ->label('Notas')
                    ->placeholder('Ej: Cambio por rotación mensual')
                    ->rows(2)
                    ->maxLength(200)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    /**
     * Define la tabla de historial de asignaciones de horario.
     */
    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('schedule')->latest('valid_from'))
            ->columns([
                TextColumn::make('schedule.name')
                    ->label('Horario')
                    ->weight('medium')
                    ->icon('heroicon-o-clock'),

                TextColumn::make('schedule.shift_type')
                    ->label('Jornada')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Schedule::getShiftTypeLabels()[$state] ?? ($state ?? '—'))
                    ->color(fn ($state) => Schedule::getShiftTypeColors()[$state] ?? 'gray'),

                TextColumn::make('valid_from')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('valid_until')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('Vigente')
                    ->color(fn ($state) => $state === null ? 'success' : null),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->placeholder('—')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Asignar Horario')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Asignar Horario al Empleado')
                    ->using(function (array $data): EmployeeScheduleAssignment {
                        try {
                            return ScheduleAssignmentService::assign(
                                employee: $this->getOwnerRecord(),
                                schedule: Schedule::findOrFail($data['schedule_id']),
                                validFrom: Carbon::parse($data['valid_from']),
                                validUntil: isset($data['valid_until']) ? Carbon::parse($data['valid_until']) : null,
                                notes: $data['notes'] ?? null,
                            );
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('No se pudo asignar el horario')
                                ->body(collect($e->errors())->flatten()->first())
                                ->persistent()
                                ->send();

                            throw $e;
                        }
                    })
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title('Horario asignado')
                            ->body('El horario fue asignado al empleado correctamente.')
                    ),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar Asignación')
                    ->successNotificationTitle('Asignación actualizada'),

                Action::make('close')
                    ->label('Cerrar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->tooltip('Cerrar esta asignación en la fecha de hoy')
                    ->visible(fn (EmployeeScheduleAssignment $record) => $record->valid_until === null)
                    ->requiresConfirmation()
                    ->modalHeading('Cerrar asignación')
                    ->modalDescription('Se cerrará este horario con fecha de hoy. El empleado quedará sin horario activo hasta que se le asigne uno nuevo.')
                    ->modalSubmitActionLabel('Sí, cerrar')
                    ->action(function (EmployeeScheduleAssignment $record) {
                        ScheduleAssignmentService::closeActive($record->employee, Carbon::today());

                        Notification::make()
                            ->success()
                            ->title('Asignación cerrada')
                            ->body('El horario fue cerrado con fecha de hoy.')
                            ->send();
                    }),

                DeleteAction::make()
                    ->modalHeading('Eliminar asignación')
                    ->modalDescription('¿Estás seguro de que deseas eliminar esta asignación del historial?')
                    ->modalSubmitActionLabel('Sí, eliminar')
                    ->successNotificationTitle('Asignación eliminada'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->modalHeading('Eliminar asignaciones')
                        ->modalDescription('¿Estás seguro de que deseas eliminar las asignaciones seleccionadas?')
                        ->modalSubmitActionLabel('Sí, eliminar'),
                ]),
            ])
            ->defaultSort('valid_from', 'desc')
            ->emptyStateHeading('Sin historial de horarios')
            ->emptyStateDescription('Asigna un horario al empleado para comenzar el historial.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
