<?php

namespace App\Filament\Resources\AttendanceDayResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';
    protected static ?string $title = 'Marcaciones';
    protected static ?string $modelLabel = 'Marcación';
    protected static ?string $pluralModelLabel = 'Marcaciones';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event_type')
            ->columns([
                Tables\Columns\TextColumn::make('recorded_at')
                    ->label('Marcado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'check_in' => 'Entrada jornada',
                        'break_start' => 'Inicio descanso',
                        'break_end' => 'Fin descanso',
                        'check_out' => 'Salida jornada',
                        default => 'Desconocido',
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'check_in' => 'success',
                        'break_start' => 'warning',
                        'break_end' => 'warning',
                        'check_out' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('location')
                    ->label('Ubicación')
                    ->searchable()
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
            ->headerActions([])
            ->actions([])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
