<?php

namespace App\Filament\Resources\DeductionResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\EmployeeDeduction;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;

class EmployeeDeductionsRelationManager extends RelationManager
{
    protected static string $relationship = 'employeeDeductions';
    protected static ?string $title = 'Empleados Asignados';
    protected static ?string $modelLabel = 'asignación';
    protected static ?string $pluralModelLabel = 'asignaciones';

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
                            ->whereDoesntHave('employeeDeductions', function ($q) {
                                $q->where('deduction_id', $this->getOwnerRecord()->id)
                                    ->whereNull('end_date');
                            })
                    )
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->first_name . ' ' . $record->last_name . ' (' . $record->ci . ')')
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->required()
                    ->preload()
                    ->native(false)
                    ->helperText('Solo se muestran empleados activos sin esta deducción')
                    ->hiddenOn('edit'),

                DatePicker::make('start_date')
                    ->label('Fecha de Inicio')
                    ->required()
                    ->default(now())
                    ->maxDate(now())
                    ->native(false)
                    ->displayFormat('d/m/Y'),

                DatePicker::make('end_date')
                    ->label('Fecha de Fin')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->after('start_date')
                    ->helperText('Dejar vacío si la deducción está activa'),

                TextInput::make('custom_amount')
                    ->label('Monto Personalizado')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999999999.99)
                    ->step(0.01)
                    ->prefix('₲')
                    ->helperText('Opcional: anular el monto predeterminado de la deducción'),

                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(3)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ]);
    }

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
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->employee->first_name . ' ' . $record->employee->last_name)),

                TextColumn::make('employee.full_name')
                    ->label('Nombre Completo')
                    ->state(fn($record) => $record->employee->first_name . ' ' . $record->employee->last_name)
                    ->searchable(['employee.first_name', 'employee.last_name'])
                    ->sortable(['employee.first_name', 'employee.last_name'])
                    ->weight('bold'),

                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('employee.activeContract.position.name')
                    ->label('Cargo')
                    ->default('-')
                    ->badge()
                    ->color('info'),

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
                    ->default(null),

                TextColumn::make('custom_amount')
                    ->label('Monto Personalizado')
                    ->money('PYG', locale: 'es_PY')
                    ->placeholder('Monto por defecto')
                    ->default(null)
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Estado')
                    ->getStateUsing(fn($record) => $record->isActive())
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->queries(
                        true: fn(Builder $query) => $query->active(),
                        false: fn(Builder $query) => $query->inactive(),
                    )
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Asignar Empleado')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('Asignar Empleado a la Deducción')
                    ->mutateFormDataUsing(function (array $data) {
                        $data['deduction_id'] = $this->getOwnerRecord()->id;
                        $data['notes'] = $data['notes'] ?? 'Asignado desde el panel de deducciones';
                        return $data;
                    })
                    ->successNotificationTitle('Empleado asignado exitosamente'),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading('Editar Asignación')
                    ->successNotificationTitle('Asignación actualizada exitosamente'),

                Action::make('deactivate')
                    ->label('Remover')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn($record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Remover Empleado de la Deducción')
                    ->modalDescription('¿Está seguro de que desea remover este empleado de la deducción? Esta acción marcará la fecha de fin.')
                    ->modalSubmitActionLabel('Sí, remover')
                    ->action(function (EmployeeDeduction $record) {
                        if ($record->deactivate()) {
                            Notification::make()
                                ->success()
                                ->title('Empleado removido exitosamente')
                                ->body('La fecha de fin ha sido establecida.')
                                ->send();
                        }
                    }),

                Action::make('reactivate')
                    ->label('Reactivar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => !$record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Reactivar Asignación')
                    ->modalDescription('¿Está seguro de que desea reactivar esta asignación? Se quitará la fecha de fin.')
                    ->modalSubmitActionLabel('Sí, reactivar')
                    ->action(function (EmployeeDeduction $record) {
                        if ($record->reactivate()) {
                            Notification::make()
                                ->success()
                                ->title('Asignación reactivada exitosamente')
                                ->body('La fecha de fin ha sido removida.')
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->label('Eliminar')
                    ->modalHeading('Eliminar Asignación')
                    ->modalDescription('¿Está seguro de que desea eliminar permanentemente esta asignación del historial?')
                    ->successNotificationTitle('Asignación eliminada del historial'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('deactivate')
                        ->label('Remover seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Remover Empleados de la Deducción')
                        ->modalDescription('¿Está seguro de que desea remover los empleados seleccionados? Esta acción marcará la fecha de fin.')
                        ->modalSubmitActionLabel('Sí, remover')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->isActive() && $record->deactivate()) {
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Empleados removidos exitosamente')
                                ->body("{$count} empleado(s) removido(s) de la deducción.")
                                ->send();
                        }),

                    DeleteBulkAction::make()
                        ->label('Eliminar del historial')
                        ->modalHeading('Eliminar Asignaciones')
                        ->modalDescription('¿Está seguro de que desea eliminar permanentemente estas asignaciones del historial?')
                        ->successNotificationTitle('Asignaciones eliminadas del historial'),
                ]),
            ])
            ->emptyStateHeading('No hay empleados asignados')
            ->emptyStateDescription('Comience asignando empleados a esta deducción.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
