<?php

namespace App\Filament\Resources;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Schedule;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Resources\ScheduleResource\Pages;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;
    protected static ?string $navigationGroup = 'Empresa';
    protected static ?string $navigationLabel = 'Horarios';
    protected static ?string $label = 'Horario';
    protected static ?string $pluralLabel = 'Horarios';
    protected static ?string $slug = 'horarios';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Horario')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->placeholder('Ejemplo: Horario Estándar')
                            ->required()
                            ->maxLength(60)
                            ->columnSpan(1),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Descripción general del horario')
                            ->rows(2)
                            ->maxLength(100)
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Configuración de Días')
                    ->schema([
                        Repeater::make('days')
                            ->relationship()
                            ->label('Días Laborales')
                            ->schema([
                                Select::make('day_of_week')
                                    ->label('Día')
                                    ->options([
                                        1 => 'Lunes',
                                        2 => 'Martes',
                                        3 => 'Miércoles',
                                        4 => 'Jueves',
                                        5 => 'Viernes',
                                        6 => 'Sábado',
                                        7 => 'Domingo',
                                    ])
                                    ->native(false)
                                    ->required()
                                    ->distinct()
                                    ->columnSpan(1),

                                TimePicker::make('start_time')
                                    ->label('Entrada')
                                    ->native(false)
                                    ->seconds(false)
                                    ->required()
                                    ->columnSpan(1),

                                TimePicker::make('end_time')
                                    ->label('Salida')
                                    ->native(false)
                                    ->seconds(false)
                                    ->required()
                                    ->after('start_time')
                                    ->columnSpan(1),

                                Repeater::make('breaks')
                                    ->relationship()
                                    ->label('Descansos')
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Nombre')
                                            ->placeholder('Ejemplo: Almuerzo')
                                            ->maxLength(60)
                                            ->required(),

                                        TimePicker::make('start_time')
                                            ->label('Inicio')
                                            ->native(false)
                                            ->seconds(false)
                                            ->required(),

                                        TimePicker::make('end_time')
                                            ->label('Fin')
                                            ->native(false)
                                            ->seconds(false)
                                            ->required()
                                            ->after('start_time'),
                                    ])
                                    ->columns(3)
                                    ->minItems(0)
                                    ->maxItems(6)
                                    ->defaultItems(0)
                                    ->collapsible()
                                    ->collapsed()
                                    ->cloneable()
                                    ->addActionLabel('Agregar Descanso')
                                    ->deletable()
                                    ->reorderable()
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->required()
                            ->minItems(1)
                            ->maxItems(7)
                            ->defaultItems(1)
                            ->collapsible()
                            ->cloneable()
                            ->addActionLabel('Agregar Día')
                            ->deletable()
                            ->reorderable()
                            ->itemLabel(
                                fn(array $state): ?string =>
                                isset($state['day_of_week'])
                                    ? match ($state['day_of_week']) {
                                        1 => 'Lunes',
                                        2 => 'Martes',
                                        3 => 'Miércoles',
                                        4 => 'Jueves',
                                        5 => 'Viernes',
                                        6 => 'Sábado',
                                        7 => 'Domingo',
                                        default => null
                                    }
                                    : null
                            ),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-clock')
                    ->iconColor('primary'),

                TextColumn::make('days_count')
                    ->label('Días')
                    ->counts('days')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('employees_count')
                    ->label('Empleados Asignados')
                    ->counts('employees')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->sortable(),

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
                //
            ])
            ->actions([
                Action::make('viewEmployees')
                    ->label('Mostrar Empleados')
                    ->icon('heroicon-o-users')
                    ->color('info')
                    ->modalHeading(fn(Schedule $record) => "Empleados con horario: {$record->name}")
                    ->modalContent(function (Schedule $record) {
                        $employees = $record->employees()
                            ->orderBy('first_name')
                            ->orderBy('last_name')
                            ->get();

                        return view('filament.modals.schedule-employees', [
                            'schedule' => $record,
                            'employees' => $employees
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalWidth('3xl')
                    ->slideOver(),
                Action::make('assignToEmployees')
                    ->label('Asignar')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->form([
                        Section::make('Seleccionar Empleados')
                            ->description('Seleccione los empleados a los que desea asignar este horario')
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
                                    ->afterStateUpdated(fn(callable $set) => $set('employee_ids', []))
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
                                    ->afterStateUpdated(fn(callable $set) => $set('employee_ids', []))
                                    ->columnSpan(1),

                                Select::make('employee_ids')
                                    ->label('Empleados')
                                    ->options(function (callable $get) {
                                        $query = Employee::query();

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
                                            ->mapWithKeys(fn($employee) => [
                                                $employee->id => "{$employee->full_name} - CI: {$employee->ci}"
                                            ]);
                                    })
                                    ->multiple()
                                    ->searchable()
                                    ->required()
                                    ->native(false)
                                    ->helperText('Puede seleccionar múltiples empleados')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->modalHeading('Asignar Horario a Empleados')
                    ->modalSubmitActionLabel('Asignar Horario')
                    ->modalWidth('2xl')
                    ->action(function (Schedule $record, array $data) {
                        try {
                            $employeeIds = $data['employee_ids'] ?? [];

                            if (empty($employeeIds)) {
                                Notification::make()
                                    ->warning()
                                    ->title('No hay empleados seleccionados')
                                    ->body('Debe seleccionar al menos un empleado.')
                                    ->send();
                                return;
                            }

                            // Contar empleados que ya tienen este horario
                            $alreadyAssigned = Employee::whereIn('id', $employeeIds)
                                ->where('schedule_id', $record->id)
                                ->count();

                            // Actualizar solo los empleados que no tienen este horario
                            $updated = Employee::whereIn('id', $employeeIds)
                                ->where(function ($query) use ($record) {
                                    $query->whereNull('schedule_id')
                                        ->orWhere('schedule_id', '!=', $record->id);
                                })
                                ->update(['schedule_id' => $record->id]);

                            if ($updated > 0) {
                                $message = "El horario \"{$record->name}\" fue asignado a {$updated} empleado(s).";
                                if ($alreadyAssigned > 0) {
                                    $message .= " {$alreadyAssigned} empleado(s) ya tenían este horario asignado.";
                                }

                                Notification::make()
                                    ->success()
                                    ->title('Horario asignado exitosamente')
                                    ->body($message)
                                    ->send();
                            } else {
                                Notification::make()
                                    ->info()
                                    ->title('Sin cambios')
                                    ->body('Todos los empleados seleccionados ya tienen este horario asignado.')
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al asignar el horario')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No hay horarios registrados')
            ->emptyStateDescription('Comienza a crear horarios de trabajo para asignar a los empleados.')
            ->emptyStateIcon('heroicon-o-clock');
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSchedules::route('/'),
            'create' => Pages\CreateSchedule::route('/create'),
            'view' => Pages\ViewSchedule::route('/{record}'),
            'edit' => Pages\EditSchedule::route('/{record}/edit'),
        ];
    }
}
