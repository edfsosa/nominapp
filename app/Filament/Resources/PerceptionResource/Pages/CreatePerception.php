<?php

namespace App\Filament\Resources\PerceptionResource\Pages;

use App\Filament\Resources\PerceptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerception extends CreateRecord
{
    protected static string $resource = PerceptionResource::class;

    /**
     * Obtiene la URL a la que se redirigirá después de crear el registro.
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * Obtiene el título de la notificación que se mostrará después de crear el registro.
     *
     * @return string|null
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Percepción creada exitosamente';
    }
}
