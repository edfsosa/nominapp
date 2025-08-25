<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduleResource\Pages;
use App\Filament\Resources\ScheduleResource\RelationManagers;
use App\Models\Schedule;
use Filament\Forms;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;
    protected static ?string $navigationLabel = 'Horarios';
    protected static ?string $label = 'Horario';
    protected static ?string $pluralLabel = 'Horarios';
    protected static ?string $slug = 'horarios';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationGroup = 'Empresa';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(60),
                TextInput::make('description')
                    ->label('Descripción')
                    ->maxLength(100)
                    ->default(null),
                Repeater::make('days')
                    ->relationship()
                    ->label('Días')
                    ->schema([
                        Select::make('day_of_week')
                            ->label('Día de la semana')
                            ->options([
                                '0' => 'Domingo',
                                '1' => 'Lunes',
                                '2' => 'Martes',
                                '3' => 'Miércoles',
                                '4' => 'Jueves',
                                '5' => 'Viernes',
                                '6' => 'Sábado',
                            ])
                            ->native(false)
                            ->required(),
                        TimePicker::make('start_time')
                            ->label('Hora de inicio')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->seconds(false)
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('Hora de fin')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->seconds(false)
                            ->required(),
                        Repeater::make('breaks')
                            ->relationship()
                            ->label('Descansos')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->maxLength(60)
                                    ->required(),
                                TimePicker::make('start_time')
                                    ->label('Inicio descanso')
                                    ->native(false)
                                    ->closeOnDateSelection()
                                    ->seconds(false)
                                    ->required(),
                                TimePicker::make('end_time')
                                    ->label('Fin descanso')
                                    ->native(false)
                                    ->closeOnDateSelection()
                                    ->seconds(false)
                                    ->required(),
                            ])
                            ->columns(3)
                            ->required()
                            ->minItems(1)
                            ->maxItems(6)
                            ->collapsible()
                            ->cloneable()
                            ->addActionLabel('Agregar')
                            ->deletable()
                            ->reorderable()
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->required()
                    ->minItems(5)
                    ->maxItems(7)
                    ->cloneable()
                    ->addActionLabel('Agregar')
                    ->deletable()
                    ->reorderable(),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSchedules::route('/'),
        ];
    }
}
