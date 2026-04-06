<?php

namespace App\Filament\Resources\RotationPatternResource\Pages;

use App\Filament\Resources\RotationPatternResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/** Página de creación de patrón de rotación. */
class CreateRotationPattern extends CreateRecord
{
    protected static string $resource = RotationPatternResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Patrón creado')
            ->body('El patrón de rotación fue creado correctamente.');
    }
}
