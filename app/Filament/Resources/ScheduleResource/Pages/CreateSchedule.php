<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Filament\Resources\ScheduleResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSchedule extends CreateRecord
{
    protected static string $resource = ScheduleResource::class;

    /**
     * Capitaliza el nombre del horario antes de crear el registro. Es decir, convierte la primera letra de cada palabra a mayúscula.
      * @param array<string, mixed> $data
      * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['name'])) {
            $data['name'] = preg_replace_callback('/(?:^|\s)\S/u', fn($m) => mb_strtoupper($m[0], 'UTF-8'), $data['name']);
        }

        return $data;
    }

    /**
     * Redirige a la página de detalle del horario recién creado.
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Envía una notificación de éxito después de crear un nuevo horario.
     * @return \Filament\Notifications\Notification|null
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Horario creado')
            ->body("El horario \"{$this->record->name}\" ha sido creado correctamente.");
    }
}
