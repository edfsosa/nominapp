<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\PositionResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePosition extends CreateRecord
{
    protected static string $resource = PositionResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Cargo creado')
            ->body("El cargo \"{$this->record->name}\" ha sido creado correctamente.")
            ->send();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Limpiar espacios en blanco y que la primera letra de cada palabra sea mayúscula
        $data['name'] = ucwords(trim($data['name']));
        return $data;
    }
}
