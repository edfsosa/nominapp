<?php

namespace App\Filament\Resources\AttendanceDayResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
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
                TextColumn::make('recorded_at')
                    ->label('Marcado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('event_type')
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
                TextColumn::make('location')
                    ->label('Ubicación')
                    ->searchable()
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
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('map_location')
                    ->label('Ver en mapa')
                    // el valor de location es un json con latitud y longitud
                    ->url(
                        function ($record) {
                            $latitude = $record->location['lat'] ?? null;
                            $longitude = $record->location['lng'] ?? null;
                            if ($latitude && $longitude) {
                                return "https://www.google.com/maps?q={$latitude},{$longitude}";
                            }
                            return null;
                        }
                    )
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-map-pin'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
