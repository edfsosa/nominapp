<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BranchesRelationManager extends RelationManager
{
    protected static string $relationship = 'branches';

    protected static ?string $title = 'Sucursales';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información general')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre de la sucursal')
                                    ->required()
                                    ->maxLength(100)
                                    ->columnSpanFull(),

                                TextInput::make('city')
                                    ->label('Ciudad')
                                    ->required()
                                    ->maxLength(60),

                                TextInput::make('address')
                                    ->label('Dirección')
                                    ->required()
                                    ->maxLength(100),
                            ]),
                    ]),

                Section::make('Contacto')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(30),

                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->maxLength(100),
                            ]),
                    ]),

                Section::make('Ubicación')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('coordinates.lat')
                                    ->label('Latitud')
                                    ->numeric()
                                    ->minValue(-90)
                                    ->maxValue(90),

                                TextInput::make('coordinates.lng')
                                    ->label('Longitud')
                                    ->numeric()
                                    ->minValue(-180)
                                    ->maxValue(180),
                            ]),

                        Placeholder::make('help')
                            ->content('Coordenadas opcionales para ubicar la sucursal en el mapa.'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('city')
                    ->label('Ciudad')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('address')
                    ->label('Dirección')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->address),

                TextColumn::make('employees_count')
                    ->label('Empleados')
                    ->counts('employees')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Nueva Sucursal')
                    ->icon('heroicon-o-plus'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->defaultSort('name');
    }
}
