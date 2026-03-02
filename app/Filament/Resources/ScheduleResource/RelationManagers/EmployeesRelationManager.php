<?php

namespace App\Filament\Resources\ScheduleResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\Branch;
use App\Models\Employee;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\CreateAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Resources\RelationManagers\RelationManager;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';
    protected static ?string $title = 'Empleados Asignados';
    protected static ?string $modelLabel = 'empleado';
    protected static ?string $pluralModelLabel = 'empleados';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('filter_status')
                    ->label('Filtrar por Estado')
                    ->options([
                        'all' => 'Todos',
                        'active' => 'Activos',
                        'inactive' => 'Inactivos',
                        'suspended' => 'Suspendidos',
                    ])
                    ->default('active')
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn(callable $set) => $set('employee_id', null))
                    ->columnSpan(1),

                Select::make('filter_branch')
                    ->label('Filtrar por Sucursal')
                    ->options(function () {
                        return Branch::pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn(callable $set) => $set('employee_id', null))
                    ->columnSpan(1),

                Select::make('employee_id')
                    ->label('Empleado')
                    ->options(function (callable $get) {
                        $query = Employee::query()
                            ->with('schedule')
                            ->where(function ($q) {
                                $q->whereNull('schedule_id')
                                    ->orWhere('schedule_id', '!=', $this->getOwnerRecord()->id);
                            });

                        $filterStatus = $get('filter_status');
                        if ($filterStatus && $filterStatus !== 'all') {
                            $query->where('status', $filterStatus);
                        }

                        $filterBranch = $get('filter_branch');
                        if ($filterBranch) {
                            $query->where('branch_id', $filterBranch);
                        }

                        return $query
                            ->orderBy('first_name')
                            ->orderBy('last_name')
                            ->get()
                            ->mapWithKeys(function ($employee) {
                                $label = "{$employee->full_name} - CI: {$employee->ci}";

                                if ($employee->schedule_id && $employee->schedule) {
                                    $label .= " ⚠ (Horario actual: {$employee->schedule->name})";
                                }

                                return [$employee->id => $label];
                            });
                    })
                    ->searchable()
                    ->required()
                    ->native(false)
                    ->helperText('⚠ Los empleados con horario actual lo cambiarán a este horario')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('first_name')
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['activeContract.position', 'branch']))
            ->defaultSort('first_name')
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->first_name . ' ' . $record->last_name)),

                TextColumn::make('full_name')
                    ->label('Nombre Completo')
                    ->state(fn($record) => $record->first_name . ' ' . $record->last_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight('bold'),

                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('activeContract.position.name')
                    ->label('Cargo')
                    ->default('-')
                    ->badge()
                    ->color('info'),

                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->default('-')
                    ->badge()
                    ->color('success'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'suspended' => 'Suspendido',
                        default => $state
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'suspended' => 'warning',
                        default => 'gray'
                    })
                    ->sortable(),

                TextColumn::make('hire_date')
                    ->label('Fecha de Contratación')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'suspended' => 'Suspendido',
                    ])
                    ->native(false),

                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Asignar Empleado')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('Asignar Empleado al Horario')
                    ->modalWidth('2xl')
                    ->using(function (array $data) {
                        $employee = Employee::with('schedule')->find($data['employee_id']);

                        if ($employee) {
                            $previousSchedule = $employee->schedule;
                            $employee->update(['schedule_id' => $this->getOwnerRecord()->id]);

                            // Notificación personalizada según si cambió o no de horario
                            if ($previousSchedule) {
                                Notification::make()
                                    ->success()
                                    ->title('Empleado asignado exitosamente')
                                    ->body("El empleado {$employee->full_name} cambió del horario \"{$previousSchedule->name}\" a \"{$this->getOwnerRecord()->name}\".")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->success()
                                    ->title('Empleado asignado exitosamente')
                                    ->body("El empleado {$employee->full_name} fue asignado al horario \"{$this->getOwnerRecord()->name}\".")
                                    ->send();
                            }

                            return $employee;
                        }

                        return null;
                    })
                    ->successNotification(null), // Desactivar notificación por defecto
            ])
            ->actions([
                Action::make('remove')
                    ->label('Remover')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Remover Empleado del Horario')
                    ->modalDescription(fn($record) => "¿Está seguro de que desea remover a {$record->full_name} de este horario?")
                    ->modalSubmitActionLabel('Sí, remover')
                    ->action(function (Employee $record) {
                        $record->update(['schedule_id' => null]);

                        Notification::make()
                            ->success()
                            ->title('Empleado removido exitosamente')
                            ->body("El empleado {$record->full_name} ha sido removido del horario.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('remove')
                        ->label('Remover seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Remover Empleados del Horario')
                        ->modalDescription('¿Está seguro de que desea remover los empleados seleccionados de este horario?')
                        ->modalSubmitActionLabel('Sí, remover')
                        ->action(function ($records) {
                            $count = $records->count();

                            foreach ($records as $record) {
                                $record->update(['schedule_id' => null]);
                            }

                            Notification::make()
                                ->success()
                                ->title('Empleados removidos exitosamente')
                                ->body("{$count} empleado(s) removido(s) del horario.")
                                ->send();
                        }),
                ]),
            ])
            ->emptyStateHeading('No hay empleados asignados')
            ->emptyStateDescription('Comience asignando empleados a este horario.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
