<?php

namespace App\Filament\Resources\AttendanceDayResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;

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
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->icon('heroicon-o-clock')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('event_type')
                    ->label('Tipo de Evento')
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
                        'break_end' => 'info',
                        'check_out' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn($state) => match ($state) {
                        'check_in' => 'heroicon-o-arrow-right-on-rectangle',
                        'break_start' => 'heroicon-o-pause-circle',
                        'break_end' => 'heroicon-o-play-circle',
                        'check_out' => 'heroicon-o-arrow-left-on-rectangle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('location')
                    ->label('Ubicación')
                    ->icon('heroicon-o-map-pin')
                    ->tooltip(fn($record) => $this->getLocationTooltip($record))
                    ->formatStateUsing(fn($record) => $this->formatLocation($record))
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Registrado en sistema')
                    ->dateTime('d/m/Y H:i:s')
                    ->icon('heroicon-o-calendar')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('recorded_at', 'asc')
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Tipo de evento')
                    ->options([
                        'check_in' => 'Entrada jornada',
                        'break_start' => 'Inicio descanso',
                        'break_end' => 'Fin descanso',
                        'check_out' => 'Salida jornada',
                    ])
                    ->native(false)
                    ->multiple(),
            ])
            ->headerActions([
                // Vacío intencionalmente - no se crean eventos manualmente
            ])
            ->actions([
                Tables\Actions\Action::make('view_map')
                    ->label('Mapa')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->tooltip('Ver ubicación en Google Maps')
                    ->url(fn($record) => $this->getMapUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn($record) => $this->hasValidLocation($record)),

                Tables\Actions\ViewAction::make()
                    ->label('Ver')
                    ->modalHeading('Detalle de Marcación')
                    ->modalContent(fn($record) => view('filament.resources.attendance-day.relation-managers.event-detail', [
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar marcaciones seleccionadas')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estas marcaciones? Esta acción no se puede deshacer.')
                        ->modalSubmitActionLabel('Sí, eliminar'),
                ]),
            ])
            ->emptyStateHeading('Sin marcaciones')
            ->emptyStateDescription('No hay eventos de marcación registrados para este día.')
            ->emptyStateIcon('heroicon-o-clock');
    }

    /**
     * Verifica si el registro tiene una ubicación válida
     */
    private function hasValidLocation($record): bool
    {
        if (!$record->location) {
            return false;
        }

        $location = is_string($record->location)
            ? json_decode($record->location, true)
            : $record->location;

        return isset($location['lat']) && isset($location['lng']);
    }

    /**
     * Obtiene la URL de Google Maps
     */
    private function getMapUrl($record): ?string
    {
        $location = is_string($record->location)
            ? json_decode($record->location, true)
            : $record->location;

        $latitude = $location['lat'] ?? null;
        $longitude = $location['lng'] ?? null;

        if ($latitude && $longitude) {
            return "https://www.google.com/maps?q={$latitude},{$longitude}";
        }

        return null;
    }

    /**
     * Formatea la ubicación para mostrar
     */
    private function formatLocation($record): string
    {
        if (!$record->location) {
            return 'Sin ubicación';
        }

        $location = is_string($record->location)
            ? json_decode($record->location, true)
            : $record->location;

        $latitude = $location['lat'] ?? null;
        $longitude = $location['lng'] ?? null;

        if ($latitude && $longitude) {
            return "📍 {$latitude}, {$longitude}";
        }

        return 'Sin ubicación';
    }

    /**
     * Obtiene el tooltip con información de ubicación
     */
    private function getLocationTooltip($record): string
    {
        if (!$record->location) {
            return 'No hay datos de ubicación';
        }

        $location = is_string($record->location)
            ? json_decode($record->location, true)
            : $record->location;

        $latitude = $location['lat'] ?? 'N/A';
        $longitude = $location['lng'] ?? 'N/A';

        return "Latitud: {$latitude}\nLongitud: {$longitude}\nClick en 'Mapa' para ver en Google Maps";
    }
}
