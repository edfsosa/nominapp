<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use Filament\Actions;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSchedule extends ViewRecord
{
    protected static string $resource = ScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información General')
                    ->schema([
                        TextEntry::make('name')
                            ->label('Nombre')
                            ->icon('heroicon-o-clock'),

                        TextEntry::make('description')
                            ->label('Descripción')
                            ->placeholder('Sin descripción'),
                    ])
                    ->columns(2),

                Section::make('Configuración de Días')
                    ->schema([
                        RepeatableEntry::make('days')
                            ->label('')
                            ->schema([
                                Group::make([
                                    TextEntry::make('day_of_week')
                                        ->label('Día')
                                        ->formatStateUsing(fn($state) => match ($state) {
                                            1 => 'Lunes',
                                            2 => 'Martes',
                                            3 => 'Miércoles',
                                            4 => 'Jueves',
                                            5 => 'Viernes',
                                            6 => 'Sábado',
                                            7 => 'Domingo',
                                            default => $state
                                        })
                                        ->badge()
                                        ->color('info'),

                                    TextEntry::make('start_time')
                                        ->label('Entrada')
                                        ->time('H:i')
                                        ->icon('heroicon-o-arrow-right-on-rectangle'),

                                    TextEntry::make('end_time')
                                        ->label('Salida')
                                        ->time('H:i')
                                        ->icon('heroicon-o-arrow-left-on-rectangle'),
                                ])->columns(3),

                                RepeatableEntry::make('breaks')
                                    ->label('Descansos')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Nombre'),

                                        TextEntry::make('start_time')
                                            ->label('Inicio')
                                            ->time('H:i'),

                                        TextEntry::make('end_time')
                                            ->label('Fin')
                                            ->time('H:i'),
                                    ])
                                    ->columns(3)
                                    ->columnSpanFull(),
                            ])
                            ->contained(false),
                    ]),
            ]);
    }

    public function getRelationManagers(): array
    {
        return [
            ScheduleResource\RelationManagers\EmployeesRelationManager::class,
        ];
    }
}
