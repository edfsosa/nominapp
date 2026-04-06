<?php

namespace App\Filament\Resources\ShiftTemplateResource\Pages;

use App\Filament\Resources\ShiftTemplateResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/** Página de creación de turno. */
class CreateShiftTemplate extends CreateRecord
{
    protected static string $resource = ShiftTemplateResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Turno creado')
            ->body('El turno fue creado correctamente.');
    }
}
