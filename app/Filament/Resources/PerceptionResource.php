<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\Employee;
use Filament\Forms\Form;
use App\Models\Perception;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\DB;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
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
            'index' => Pages\ListPerceptions::route('/'),
            'create' => Pages\CreatePerception::route('/create'),
            'edit' => Pages\EditPerception::route('/{record}/edit'),
        ];
    }
}
