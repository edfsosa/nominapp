<?php

namespace App\Filament\Resources\TerminalResource\Pages;

use App\Filament\Resources\TerminalResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/** Creación de una nueva terminal de marcación. */
class CreateTerminal extends CreateRecord
{
    protected static string $resource = TerminalResource::class;

    /**
     * Notificación de éxito al crear la terminal.
     *
     * @return Notification|null
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Terminal creada')
            ->body("La terminal \"{$this->record->name}\" fue creada. Configurá el dispositivo con la URL generada.");
    }
}
