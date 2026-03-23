<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\PositionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPosition extends EditRecord
{
    protected static string $resource = PositionResource::class;

    /**
     * Definir las acciones que se muestran en el encabezado de la página de edición.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->icon('heroicon-o-eye')
                ->color('primary'),

            DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->modalHeading('¿Eliminar cargo?')
                ->modalDescription('Esta acción no se puede deshacer. Se eliminará el cargo "' . $this->record->name . '" y todos los registros relacionados, como empleados asociados a este cargo.')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->successNotificationTitle('Cargo eliminado')
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }

    /**
     * Mutar los datos del formulario antes de guardar el registro, asegurando que el nombre del cargo tenga la primera letra de cada palabra en mayúscula.
     *
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['name'])) {
            $data['name'] = preg_replace_callback('/(?:^|\s)\S/u', fn($m) => mb_strtoupper($m[0], 'UTF-8'), $data['name']);
        }
        return $data;
    }

    /**
     * Definir la URL a la que se redirigirá después de guardar los cambios.
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Definir la notificación personalizada que se muestra después de guardar un cargo.
     *
     * @return Notification|null
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Cargo actualizado')
            ->body("El cargo \"{$this->record->name}\" del departamento \"{$this->record->department->name}\" ha sido actualizado correctamente.")
            ->send();
    }
}
