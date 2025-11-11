<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PerceptionResource\Pages;
use App\Filament\Resources\PerceptionResource\RelationManagers;
use App\Models\Perception;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
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
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(60),
                TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(10),
                Textarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull()
                    ->nullable(),
                Select::make('calculation')
                    ->label('Cálculo')
                    ->options([
                        'fixed'      => 'Fijo',
                        'percentage' => 'Porcentaje',
                    ])
                    ->default('fixed')
                    ->native(false)
                    ->reactive()
                    ->required(),
                TextInput::make('amount')
                    ->label('Monto Fijo')
                    ->numeric()
                    ->nullable()
                    ->visible(fn(Forms\Get $get) => $get('calculation') === 'fixed')
                    ->default(0.00),
                TextInput::make('percent')
                    ->label('Porcentaje')
                    ->numeric()
                    ->nullable()
                    ->visible(fn(Forms\Get $get) => $get('calculation') === 'percentage')
                    ->default(0.00),
                Toggle::make('is_taxable')
                    ->label('Gravable')
                    ->default(false)
                    ->inline(),
                Toggle::make('affects_ips')
                    ->label('Afecta IPS')
                    ->default(false)
                    ->inline(),
                Toggle::make('affects_irp')
                    ->label('Afecta IRP')
                    ->default(false)
                    ->inline(),
                Toggle::make('is_active')
                    ->label('Activo')
                    ->default(true)
                    ->inline(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('calculation')
                    ->label('Cálculo')
                    ->formatStateUsing(fn($state) => $state === 'fixed' ? 'Fijo' : 'Porcentaje')
                    ->badge()
                    ->colors([
                        'success' => 'fixed',
                        'warning' => 'percentage',
                    ])
                    ->sortable()
                    ->searchable(),
                IconColumn::make('is_taxable')
                    ->label('Gravable')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
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
