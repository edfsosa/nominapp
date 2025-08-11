<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeePerceptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'employeePerceptions';
    protected static ?string $title = 'Percepciones';
    protected static ?string $modelLabel = 'Percepción';
    protected static ?string $pluralModelLabel = 'Percepciones';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('perception_id')
                    ->label('Percepción')
                    ->relationship('perception', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Desde')
                    ->native(false)
                    ->default(now())
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Hasta')
                    ->native(false)
                    ->after('start_date'),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->rows(1)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('perception.name')
                    ->label('Percepción')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Desde')
                    ->sortable()
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Hasta')
                    ->sortable()
                    ->date('d/m/Y'),
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
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
}
