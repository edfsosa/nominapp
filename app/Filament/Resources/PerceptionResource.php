<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\Employee;
use Filament\Forms\Form;
use App\Models\Perception;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Group;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Infolists\Components\ImageEntry;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Infolists\Components\RepeatableEntry;
use App\Filament\Resources\PerceptionResource\Pages;

class PerceptionResource extends Resource
{
    protected static ?string $model = Perception::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Percepciones';
    protected static ?string $label = 'Percepción';
    protected static ?string $pluralLabel = 'Percepciones';
    protected static ?string $slug = 'percepciones';
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información General')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->placeholder('Ejemplo: Bonificación por Desempeño')
                            ->required()
                            ->maxLength(60)
                            ->columnSpan(1),

                        TextInput::make('code')
                            ->label('Código')
                            ->placeholder('Ejemplo: PERC001')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Descripción detallada de la percepción')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Configuración de Cálculo')
                    ->schema([
                        Select::make('calculation')
                            ->label('Tipo de Cálculo')
                            ->options([
                                'fixed'      => 'Monto Fijo',
                                'percentage' => 'Porcentaje del Salario',
                            ])
                            ->default('fixed')
                            ->native(false)
                            ->reactive()
                            ->required()
                            ->columnSpan(1),

                        TextInput::make('amount')
                            ->label('Monto Fijo')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(999999999.99)
                            ->step(0.01)
                            ->prefix('₲')
                            ->visible(fn(Forms\Get $get) => $get('calculation') === 'fixed')
                            ->required(fn(Forms\Get $get) => $get('calculation') === 'fixed')
                            ->default(0.00)
                            ->helperText('Monto que se agregará al salario')
                            ->columnSpan(1),

                        TextInput::make('percent')
                            ->label('Porcentaje')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->visible(fn(Forms\Get $get) => $get('calculation') === 'percentage')
                            ->required(fn(Forms\Get $get) => $get('calculation') === 'percentage')
                            ->default(0.00)
                            ->helperText('Porcentaje del salario base que se agregará')
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Configuración Adicional')
                    ->schema([
                        Toggle::make('is_taxable')
                            ->label('Gravable')
                            ->helperText('Esta percepción está sujeta a impuestos')
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('affects_ips')
                            ->label('Afecta IPS')
                            ->helperText('Esta percepción afecta el cálculo del IPS')
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('affects_irp')
                            ->label('Afecta IRP')
                            ->helperText('Esta percepción afecta el cálculo del IRP')
                            ->default(false)
                            ->inline(false)
                            ->columnSpan(1),

                        Toggle::make('is_active')
                            ->label('Activo')
                            ->helperText('Habilitar o deshabilitar esta percepción')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(1),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('calculation')
                    ->label('Tipo')
                    ->formatStateUsing(fn($state) => $state === 'fixed' ? 'Fijo' : 'Porcentaje')
                    ->badge()
                    ->color(fn($state) => $state === 'fixed' ? 'success' : 'warning')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('percent')
                    ->label('Porcentaje')
                    ->formatStateUsing(fn($state) => $state ? number_format($state, 2) . '%' : '-')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),

                TextColumn::make('employees_count')
                    ->label('Empleados')
                    ->counts([
                        'employees' => fn($query) => $query->whereNull('employee_perceptions.end_date')
                    ])
                    ->badge()
                    ->color('info')
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
                SelectFilter::make('calculation')
                    ->label('Tipo de Cálculo')
                    ->options([
                        'fixed'      => 'Monto Fijo',
                        'percentage' => 'Porcentaje',
                    ])
                    ->native(false),

                TernaryFilter::make('is_taxable')
                    ->label('Gravable')
                    ->placeholder('Todos')
                    ->trueLabel('Gravables')
                    ->falseLabel('No Gravables')
                    ->native(false),

                TernaryFilter::make('affects_ips')
                    ->label('Afecta IPS')
                    ->placeholder('Todos')
                    ->trueLabel('Afecta IPS')
                    ->falseLabel('No Afecta IPS')
                    ->native(false),

                TernaryFilter::make('affects_irp')
                    ->label('Afecta IRP')
                    ->placeholder('Todos')
                    ->trueLabel('Afecta IRP')
                    ->falseLabel('No Afecta IRP')
                    ->native(false),

                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos')
                    ->native(false),
            ])
            ->actions([
                Action::make('viewAssignedEmployees')
                    ->label('Ver Empleados')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading(fn(Perception $record) => "Empleados con la percepción: {$record->name}")
                    ->infolist(function (Perception $record): array {
                        $employees = $record->employees()
                            ->wherePivot('end_date', null)
                            ->orderBy('first_name')
                            ->orderBy('last_name')
                            ->get();

                        if ($employees->isEmpty()) {
                            return [
                                \Filament\Infolists\Components\Section::make()
                                    ->schema([
                                        TextEntry::make('empty')
                                            ->label('')
                                            ->default('No hay empleados con esta percepción asignada actualmente.')
                                            ->color('gray')
                                            ->icon('heroicon-o-information-circle'),
                                    ])
                            ];
                        }

                        return [
                            RepeatableEntry::make('employees')
                                ->label('')
                                ->state($employees)
                                ->schema([
                                    Group::make([
                                        ImageEntry::make('photo')
                                            ->label('Foto')
                                            ->circular()
                                            ->size(50),

                                        TextEntry::make('full_name')
                                            ->label('Empleado')
                                            ->state(fn($record) => $record->first_name . ' ' . $record->last_name)
                                            ->icon('heroicon-o-user')
                                            ->weight('bold')
                                            ->color('primary'),

                                        TextEntry::make('ci')
                                            ->label('CI')
                                            ->badge()
                                            ->color('gray'),

                                        TextEntry::make('position.name')
                                            ->label('Cargo')
                                            ->default('-')
                                            ->badge()
                                            ->color('info'),

                                        TextEntry::make('pivot.start_date')
                                            ->label('Inicio')
                                            ->date('d/m/Y')
                                            ->badge()
                                            ->color('success'),
                                    ])->columns(5),
                                ])
                                ->contained(false),

                            \Filament\Infolists\Components\Section::make()
                                ->schema([
                                    TextEntry::make('total')
                                        ->label('')
                                        ->state('Total: ' . $employees->count() . ' empleado' . ($employees->count() != 1 ? 's' : ''))
                                        ->badge()
                                        ->color('success')
                                        ->icon('heroicon-o-users'),
                                ])
                                ->compact(),
                        ];
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->slideOver(),
                Action::make('assignToSelectedEmployees')
                    ->label('Asignar a...')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->visible(fn(Perception $record) => $record->is_active)
                    ->form([
                        Select::make('employee_ids')
                            ->label('Seleccionar Empleados')
                            ->options(function (Perception $record) {
                                // Obtener empleados activos que NO tienen esta percepción asignada
                                return Employee::where('status', 'active')
                                    ->whereDoesntHave('employeePerceptions', function ($query) use ($record) {
                                        $query->where('perception_id', $record->id)
                                            ->whereNull('end_date');
                                    })
                                    ->get()
                                    ->mapWithKeys(fn($employee) => [
                                        $employee->id => $employee->first_name . ' ' . $employee->last_name . ' (' . $employee->ci . ')'
                                    ]);
                            })
                            ->multiple()
                            ->searchable()
                            ->required()
                            ->placeholder('Seleccione uno o más empleados')
                            ->helperText('Solo se muestran empleados activos que no tienen esta percepción asignada')
                            ->native(false)
                            ->preload(),
                    ])
                    ->modalHeading('Asignar Percepción a Empleados')
                    ->modalDescription(fn(Perception $record) => "Seleccione los empleados a los que desea asignar la percepción \"{$record->name}\"")
                    ->modalSubmitActionLabel('Asignar percepción')
                    ->action(function (Perception $record, array $data) {
                        try {
                            if (empty($data['employee_ids'])) {
                                Notification::make()
                                    ->warning()
                                    ->title('No se seleccionaron empleados')
                                    ->body('Debe seleccionar al menos un empleado.')
                                    ->send();
                                return;
                            }

                            $totalAssigned = 0;

                            foreach ($data['employee_ids'] as $employeeId) {
                                $employee = Employee::find($employeeId);
                                if ($employee) {
                                    // Verificar si existe un registro con la misma fecha de inicio (histórico)
                                    $existingRecord = $employee->employeePerceptions()
                                        ->where('perception_id', $record->id)
                                        ->whereDate('start_date', now()->toDateString())
                                        ->first();

                                    if ($existingRecord) {
                                        // Si existe un registro con la misma fecha de inicio, reactivarlo
                                        $existingRecord->update([
                                            'end_date' => null,
                                            'notes' => 'Reasignado desde el panel de percepciones',
                                        ]);
                                    } else {
                                        // Crear nuevo registro
                                        $employee->employeePerceptions()->create([
                                            'perception_id' => $record->id,
                                            'start_date' => now(),
                                            'end_date' => null,
                                            'custom_amount' => null,
                                            'notes' => 'Asignado desde el panel de percepciones',
                                        ]);
                                    }
                                    $totalAssigned++;
                                }
                            }

                            Notification::make()
                                ->success()
                                ->title('Percepción asignada exitosamente')
                                ->body("La percepción \"{$record->name}\" fue asignada a {$totalAssigned} empleado(s).")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al asignar la percepción')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Action::make('assignToAllEmployees')
                    ->label('Asignar a Todos')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->visible(fn(Perception $record) => $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Asignar Percepción a Todos los Empleados')
                    ->modalDescription(fn(Perception $record) => "¿Está seguro de que desea asignar la percepción \"{$record->name}\" a TODOS los empleados activos que aún no la tienen?")
                    ->modalSubmitActionLabel('Sí, asignar a todos')
                    ->action(function (Perception $record) {
                        try {
                            // Obtener todos los empleados activos
                            $employees = Employee::where('status', 'active')->get();

                            if ($employees->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('No hay empleados activos')
                                    ->body('No hay empleados activos para asignar la percepción.')
                                    ->send();
                                return;
                            }

                            $totalAssigned = 0;
                            $alreadyAssigned = 0;

                            foreach ($employees as $employee) {
                                // Verificar si el empleado ya tiene la percepción asignada (activa)
                                $hasActivePerception = $employee->employeePerceptions()
                                    ->where('perception_id', $record->id)
                                    ->whereNull('end_date')
                                    ->exists();

                                if (!$hasActivePerception) {
                                    // Verificar si existe un registro con la misma fecha de inicio (histórico)
                                    $existingRecord = $employee->employeePerceptions()
                                        ->where('perception_id', $record->id)
                                        ->whereDate('start_date', now()->toDateString())
                                        ->first();

                                    if ($existingRecord) {
                                        // Si existe un registro con la misma fecha de inicio, reactivarlo
                                        $existingRecord->update([
                                            'end_date' => null,
                                            'notes' => 'Reasignado masivamente desde el panel de percepciones',
                                        ]);
                                    } else {
                                        // Crear nuevo registro
                                        $employee->employeePerceptions()->create([
                                            'perception_id' => $record->id,
                                            'start_date' => now(),
                                            'end_date' => null,
                                            'custom_amount' => null,
                                            'notes' => 'Asignado masivamente desde el panel de percepciones',
                                        ]);
                                    }
                                    $totalAssigned++;
                                } else {
                                    $alreadyAssigned++;
                                }
                            }

                            if ($totalAssigned > 0) {
                                Notification::make()
                                    ->success()
                                    ->title('Percepción asignada exitosamente')
                                    ->body("La percepción \"{$record->name}\" fue asignada a {$totalAssigned} empleado(s). {$alreadyAssigned} empleado(s) ya tenían esta percepción.")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->info()
                                    ->title('Sin cambios')
                                    ->body('Todos los empleados activos ya tienen esta percepción asignada.')
                                    ->send();
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al asignar la percepción')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Action::make('removeFromSelectedEmployees')
                    ->label('Remover de...')
                    ->icon('heroicon-o-user-minus')
                    ->color('warning')
                    ->form(function (Perception $record) {
                        // Verificar si hay empleados asignados
                        $hasEmployees = $record->employees()
                            ->wherePivot('end_date', null)
                            ->exists();

                        if (!$hasEmployees) {
                            return [
                                \Filament\Forms\Components\Placeholder::make('no_employees')
                                    ->label('')
                                    ->content('No hay empleados con esta percepción asignada actualmente.')
                            ];
                        }

                        return [
                            Select::make('employee_ids')
                                ->label('Seleccionar Empleados')
                                ->options(function () use ($record) {
                                    // Obtener empleados que tienen esta percepción asignada actualmente
                                    return $record->employees()
                                        ->wherePivot('end_date', null)
                                        ->get()
                                        ->mapWithKeys(fn($employee) => [
                                            $employee->id => $employee->first_name . ' ' . $employee->last_name . ' (' . $employee->ci . ')'
                                        ]);
                                })
                                ->multiple()
                                ->searchable()
                                ->required()
                                ->placeholder('Seleccione uno o más empleados')
                                ->helperText('Solo se muestran empleados que tienen esta percepción asignada')
                                ->native(false)
                                ->preload(),
                        ];
                    })
                    ->modalHeading('Remover Percepción de Empleados')
                    ->modalDescription(fn(Perception $record) => "Seleccione los empleados de los que desea remover la percepción \"{$record->name}\"")
                    ->modalSubmitActionLabel('Remover percepción')
                    ->action(function (Perception $record, array $data) {
                        try {
                            if (empty($data['employee_ids'])) {
                                Notification::make()
                                    ->warning()
                                    ->title('No se seleccionaron empleados')
                                    ->body('Debe seleccionar al menos un empleado.')
                                    ->send();
                                return;
                            }

                            $totalRemoved = 0;

                            foreach ($data['employee_ids'] as $employeeId) {
                                // Finalizar la asignación activa
                                DB::table('employee_perceptions')
                                    ->where('perception_id', $record->id)
                                    ->where('employee_id', $employeeId)
                                    ->whereNull('end_date')
                                    ->update([
                                        'end_date' => now(),
                                        'updated_at' => now(),
                                    ]);
                                $totalRemoved++;
                            }

                            Notification::make()
                                ->success()
                                ->title('Percepción removida exitosamente')
                                ->body("La percepción \"{$record->name}\" fue removida de {$totalRemoved} empleado(s).")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al remover la percepción')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                Action::make('removeFromAllEmployees')
                    ->label('Remover de Todos')
                    ->icon('heroicon-o-user-group')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Remover Percepción de Todos los Empleados')
                    ->modalDescription(fn(Perception $record) => "¿Está seguro de que desea remover la percepción \"{$record->name}\" de TODOS los empleados que la tienen asignada?")
                    ->modalSubmitActionLabel('Sí, remover de todos')
                    ->action(function (Perception $record) {
                        try {
                            // Contar las asignaciones activas antes de removerlas
                            $activeAssignments = $record->employees()
                                ->wherePivot('end_date', null)
                                ->count();

                            if ($activeAssignments === 0) {
                                Notification::make()
                                    ->info()
                                    ->title('Sin asignaciones activas')
                                    ->body('Esta percepción no está asignada a ningún empleado actualmente.')
                                    ->send();
                                return;
                            }

                            // Finalizar todas las asignaciones activas
                            DB::table('employee_perceptions')
                                ->where('perception_id', $record->id)
                                ->whereNull('end_date')
                                ->update([
                                    'end_date' => now(),
                                    'updated_at' => now(),
                                ]);

                            Notification::make()
                                ->success()
                                ->title('Percepción removida exitosamente')
                                ->body("La percepción \"{$record->name}\" fue removida de {$activeAssignments} empleado(s).")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Error al remover la percepción')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No hay percepciones registradas')
            ->emptyStateDescription('Comienza a agregar percepciones para gestionar bonificaciones y otros ingresos adicionales de los empleados.')
            ->emptyStateIcon('heroicon-o-plus-circle');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePerceptions::route('/'),
        ];
    }
}
