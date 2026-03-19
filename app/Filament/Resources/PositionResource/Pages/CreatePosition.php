<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\PositionResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePosition extends CreateRecord
{
    protected static string $resource = PositionResource::class;

    /**
     * Mutar los datos del formulario antes de crear el registro, asegurando que el nombre del cargo tenga la primera letra de cada palabra en mayúscula.
     *
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['name'])) {
            $data['name'] = preg_replace_callback('/(?:^|\s)\S/u', fn($m) => mb_strtoupper($m[0], 'UTF-8'), $data['name']);
        }
        return $data;
    }

    /**
     * Definir la notificación personalizada que se muestra después de crear un cargo.
     *
     * @return Notification|null
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Cargo creado')
            ->body("El cargo \"{$this->record->name}\" del departamento \"{$this->record->department->name}\" ha sido creado exitosamente.")
            ->send();
    }
}
