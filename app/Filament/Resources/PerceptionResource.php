<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerceptionResource\Pages;
use App\Filament\Resources\PerceptionResource\RelationManagers;
use App\Models\Perception;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PerceptionResource extends Resource
{
    protected static ?string $model = Perception::class;
    protected static ?string $navigationGroup = 'Definiciones';
    protected static ?string $navigationLabel = 'Percepciones';
    protected static ?string $label = 'Percepción';
    protected static ?string $pluralLabel = 'Percepciones';
    protected static ?string $slug = 'percepciones';
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(60),
                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull()
                    ->nullable(),
                Forms\Components\Select::make('calculation')
                    ->label('Cálculo')
                    ->options([
                        'fixed'      => 'Fijo',
                        'percentage' => 'Porcentaje',
                    ])
                    ->default('fixed')
                    ->native(false)
                    ->reactive()
                    ->required(),
                Forms\Components\TextInput::make('amount')
                    ->label('Monto Fijo')
                    ->numeric()
                    ->nullable()
                    ->visible(fn(Forms\Get $get) => $get('calculation') === 'fixed')
                    ->default(0.00),
                Forms\Components\TextInput::make('percent')
                    ->label('Porcentaje')
                    ->numeric()
                    ->nullable()
                    ->visible(fn(Forms\Get $get) => $get('calculation') === 'percentage')
                    ->default(0.00),
                Forms\Components\Toggle::make('is_taxable')
                    ->label('Gravable')
                    ->default(false)
                    ->inline(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->inline(),
            ]);
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
                Tables\Columns\TextColumn::make('calculation')
                    ->label('Cálculo')
                    ->formatStateUsing(fn($state) => $state === 'fixed' ? 'Fijo' : 'Porcentaje')
                    ->badge()
                    ->colors([
                        'success' => 'fixed',
                        'warning' => 'percentage',
                    ])
                    ->sortable()
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_taxable')
                    ->label('Gravable')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),
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
            'index' => Pages\ManagePerceptions::route('/'),
        ];
    }
}
