<?php

namespace App\Filament\Resources\AttendanceDayResource\RelationManagers;

use Filament\Tables;
use Filament\Tables\Table;
use App\Models\AttendanceEvent;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Resources\RelationManagers\RelationManager;

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
                    ->formatStateUsing(fn($state) => AttendanceEvent::getEventTypeLabel($state))
                    ->badge()
                    ->color(fn($state) => AttendanceEvent::getEventTypeColor($state))
                    ->icon(fn($state) => AttendanceEvent::getEventTypeIcon($state))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('location')
                    ->label('Ubicación')
                    ->icon('heroicon-o-map-pin')
                    ->tooltip(fn(AttendanceEvent $record) => $record->getLocationTooltip())
                    ->formatStateUsing(fn(AttendanceEvent $record) => $record->getFormattedLocation())
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
                    ->options(AttendanceEvent::getEventTypeOptions())
                    ->native(false)
                    ->multiple(),
            ])
            ->headerActions([
                // Vacío intencionalmente - no se crean eventos manualmente
            ])
            ->actions([
                Action::make('view_map')
                    ->label('Mapa')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->tooltip('Ver ubicación en Google Maps')
                    ->url(fn(AttendanceEvent $record) => $record->getMapUrl())
                    ->openUrlInNewTab()
                    ->visible(fn(AttendanceEvent $record) => $record->hasValidLocation()),

                ViewAction::make()
                    ->label('Ver')
                    ->modalHeading('Detalle de Marcación')
                    ->modalContent(fn(AttendanceEvent $record) => view('filament.resources.attendance-day.relation-managers.event-detail', [
                        'record' => $record,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
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
}
