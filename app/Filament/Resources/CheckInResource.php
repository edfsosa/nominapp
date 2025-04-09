<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CheckInResource\Pages;
use App\Filament\Resources\CheckInResource\RelationManagers;
use App\Models\CheckIn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CheckInResource extends Resource
{
    protected static ?string $model = CheckIn::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.name')->label('Empleado')->searchable(),
                Tables\Columns\ImageColumn::make('photo_path')->label('Selfie'),
                Tables\Columns\TextColumn::make('type')->label('Tipo')->badge(),
                Tables\Columns\TextColumn::make('latitude')->label('Lat'),
                Tables\Columns\TextColumn::make('longitude')->label('Lng'),
                Tables\Columns\TextColumn::make('created_at')->label('Fecha')->dateTime(),

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
            'index' => Pages\ManageCheckIns::route('/'),
        ];
    }
}
