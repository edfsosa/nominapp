<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Models\Schedule;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
