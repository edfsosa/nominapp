<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerceptionResource\Pages;
use App\Models\Perception;
use App\Models\Employee;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

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

    /**
     * Define el formulario para crear y editar percepciones
     *
     * @param Form $form
     * @return Form
     */
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
                            ->placeholder('Ejemplo: BON-DES')
                            ->required()
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->columnSpan(1),

                        Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Ejemplo: Bonificación por Desempeño otorgada trimestralmente según evaluación de desempeño')
                            ->maxLength(255)
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
                            ->live()
                            ->required()
                            ->helperText('Define cómo se calculará esta percepción')
                            ->columnSpan(1),

                        TextInput::make('amount')
                            ->label('Monto Fijo')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(999999999.99)
                            ->step(1)
                            ->prefix('₲')
                            ->visible(fn(Get $get) => $get('calculation') === 'fixed')
                            ->required(fn(Get $get) => $get('calculation') === 'fixed')
                            ->helperText('Monto que se agregará al salario')
                            ->columnSpan(1),

                        TextInput::make('percent')
                            ->label('Porcentaje')
                            ->numeric()
                            ->minValue(0.01)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%')
                            ->visible(fn(Get $get) => $get('calculation') === 'percentage')
                            ->required(fn(Get $get) => $get('calculation') === 'percentage')
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

    /**
     * Define la tabla para listar percepciones
     *
     * @param Table $table
     * @return Table
     */
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
                    ->formatStateUsing(fn($state) => match ($state) {
                        'fixed'      => 'Fijo',
                        'percentage' => 'Porcentaje',
                        default      => '-',
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'fixed'      => 'primary',
                        'percentage' => 'secondary',
                        default      => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('percent')
                    ->label('Porcentaje')
                    ->formatStateUsing(fn($state) => Perception::formatPercent($state))
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('is_taxable')
                    ->label('Gravable')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('active_employees_count')
                    ->label('Empleados')
                    ->counts('activeEmployees')
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
                            $allActiveIds = Employee::where('status', 'active')->pluck('id');

                            if ($allActiveIds->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('No hay empleados activos')
                                    ->body('No hay empleados activos para asignar la percepción.')
                                    ->send();
                                return;
                            }

                            $alreadyActiveIds = DB::table('employee_perceptions')
                                ->where('perception_id', $record->id)
                                ->whereNull('end_date')
                                ->pluck('employee_id');

                            $toProcessIds = $allActiveIds->diff($alreadyActiveIds);
                            $alreadyAssigned = $alreadyActiveIds->count();

                            if ($toProcessIds->isEmpty()) {
                                Notification::make()
                                    ->info()
                                    ->title('Sin cambios')
                                    ->body('Todos los empleados activos ya tienen esta percepción asignada.')
                                    ->send();
                                return;
                            }

                            DB::transaction(function () use ($record, $toProcessIds) {
                                $now   = now();
                                $today = $now->toDateString();

                                // Reactivar registros con fecha de inicio hoy (edge case histórico)
                                $reactivateIds = DB::table('employee_perceptions')
                                    ->where('perception_id', $record->id)
                                    ->whereIn('employee_id', $toProcessIds)
                                    ->whereDate('start_date', $today)
                                    ->pluck('employee_id');

                                if ($reactivateIds->isNotEmpty()) {
                                    DB::table('employee_perceptions')
                                        ->where('perception_id', $record->id)
                                        ->whereIn('employee_id', $reactivateIds)
                                        ->whereDate('start_date', $today)
                                        ->update([
                                            'end_date'   => null,
                                            'notes'      => 'Reasignado masivamente desde el panel de percepciones',
                                            'updated_at' => $now,
                                        ]);
                                }

                                // Insertar nuevos registros en bulk
                                $newIds = $toProcessIds->diff($reactivateIds);
                                if ($newIds->isNotEmpty()) {
                                    DB::table('employee_perceptions')->insert(
                                        $newIds->map(fn($id) => [
                                            'employee_id'   => $id,
                                            'perception_id' => $record->id,
                                            'start_date'    => $today,
                                            'end_date'      => null,
                                            'custom_amount' => null,
                                            'notes'         => 'Asignado masivamente desde el panel de percepciones',
                                            'created_at'    => $now,
                                            'updated_at'    => $now,
                                        ])->values()->toArray()
                                    );
                                }
                            });

                            Notification::make()
                                ->success()
                                ->title('Percepción asignada exitosamente')
                                ->body("La percepción \"{$record->name}\" fue asignada a {$toProcessIds->count()} empleado(s). {$alreadyAssigned} empleado(s) ya tenían esta percepción.")
                                ->send();
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
                            $activeAssignments = $record->activeEmployees()->count();

                            if ($activeAssignments === 0) {
                                Notification::make()
                                    ->info()
                                    ->title('Sin asignaciones activas')
                                    ->body('Esta percepción no está asignada a ningún empleado actualmente.')
                                    ->send();
                                return;
                            }

                            DB::table('employee_perceptions')
                                ->where('perception_id', $record->id)
                                ->whereNull('end_date')
                                ->update([
                                    'end_date'   => now(),
                                    'notes'      => 'Removido masivamente desde el panel de percepciones',
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
            ->bulkActions([])
            ->emptyStateHeading('No hay percepciones registradas')
            ->emptyStateDescription('Comienza a agregar percepciones para gestionar los adicionales en los salarios de los empleados.')
            ->emptyStateIcon('heroicon-o-plus-circle');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoSection::make('Información General')
                    ->schema([
                        TextEntry::make('code')
                            ->label('Código')
                            ->badge()
                            ->color('gray')
                            ->copyable()
                            ->copyMessage('Código copiado'),

                        TextEntry::make('name')
                            ->label('Nombre'),

                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('Sin descripción')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                InfoSection::make('Configuración de Cálculo')
                    ->schema([
                        TextEntry::make('calculation')
                            ->label('Tipo de Cálculo')
                            ->badge()
                            ->formatStateUsing(fn($state) => match ($state) {
                                'fixed'      => 'Monto Fijo',
                                'percentage' => 'Porcentaje del Salario',
                                default      => '-',
                            })
                            ->color(fn($state) => match ($state) {
                                'fixed'      => 'success',
                                'percentage' => 'warning',
                                default      => 'gray',
                            }),

                        TextEntry::make('amount')
                            ->label('Monto Fijo')
                            ->money('PYG', locale: 'es_PY')
                            ->placeholder('-')
                            ->visible(fn(Perception $record) => $record->calculation === 'fixed'),

                        TextEntry::make('percent')
                            ->label('Porcentaje')
                            ->formatStateUsing(fn($state) => $state ? number_format($state, 2) . '%' : '-')
                            ->visible(fn(Perception $record) => $record->calculation === 'percentage'),
                    ])
                    ->columns(2),

                InfoSection::make('Configuración Adicional')
                    ->schema([
                        IconEntry::make('is_taxable')
                            ->label('Gravable')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        IconEntry::make('affects_ips')
                            ->label('Afecta IPS')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        IconEntry::make('affects_irp')
                            ->label('Afecta IRP')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),

                        IconEntry::make('is_active')
                            ->label('Estado')
                            ->boolean()
                            ->trueIcon('heroicon-o-check-circle')
                            ->falseIcon('heroicon-o-x-circle')
                            ->trueColor('success')
                            ->falseColor('danger'),
                    ])
                    ->columns(4),

                InfoSection::make('Auditoría')
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Creado')
                            ->dateTime('d/m/Y H:i'),

                        TextEntry::make('updated_at')
                            ->label('Última Actualización')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPerceptions::route('/'),
            'create' => Pages\CreatePerception::route('/create'),
            'view'   => Pages\ViewPerception::route('/{record}'),
            'edit'   => Pages\EditPerception::route('/{record}/edit'),
        ];
    }
}
