<?php

namespace App\Filament\Resources\PerceptionResource\Pages;

use App\Filament\Resources\PerceptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerceptions extends ListRecords
{
    protected static string $resource = PerceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear Percepción')
                ->icon('heroicon-o-plus-circle')
                ->successNotificationTitle('Percepción creada exitosamente'),
        ];
    }
}
