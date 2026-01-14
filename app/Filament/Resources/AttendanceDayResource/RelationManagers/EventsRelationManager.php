<?php

namespace App\Filament\Resources\AttendanceDayResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\AttendanceEvent;
use Filament\Forms\Components\Select;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Resources\RelationManagers\RelationManager;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';
    protected static ?string $title = 'Marcaciones';
    protected static ?string $modelLabel = 'Marcación';
    protected static ?string $pluralModelLabel = 'Marcaciones';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('event_type')
                    ->label('Tipo de Evento')
                    ->options(AttendanceEvent::getEventTypeOptions())
                    ->native(false)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event_type')
            ->columns([
                TextColumn::make('recorded_at')
                    ->label('Fecha y Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->icon('heroicon-o-clock')
                    ->sortable(),

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
            ->actions([
                ViewAction::make()
                    ->modalHeading('Detalle de Marcación')
                    ->modalContent(fn(AttendanceEvent $record) => view('filament.resources.attendance-day.relation-managers.event-detail', [
                        'record' => $record,
                    ])),

                EditAction::make()
                    ->modalHeading('Editar Marcación')
                    ->successNotificationTitle('Marcación actualizada exitosamente'),

                /* Action::make('view_map')
                    ->label('Mapa')
                    ->icon('heroicon-o-map-pin')
                    ->color('info')
                    ->tooltip('Ver ubicación en Google Maps')
                    ->url(fn(AttendanceEvent $record) => $record->getMapUrl())
                    ->openUrlInNewTab()
                    ->visible(fn(AttendanceEvent $record) => $record->hasValidLocation()), */

                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->modalHeading('Eliminar Marcación')
                    ->modalDescription('¿Está seguro de que desea eliminar esta marcación? Esta acción no se puede deshacer.')
                    ->successNotificationTitle('Marcación eliminada exitosamente'),
            ])
            ->emptyStateHeading('Sin marcaciones')
            ->emptyStateDescription('No hay eventos de marcación registrados para este día.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
