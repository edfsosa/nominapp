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

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Cargo eliminado')
                        ->body('El cargo ha sido eliminado correctamente.')
                ),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Cargo actualizado')
            ->body("El cargo \"{$this->record->name}\" ha sido actualizado correctamente.");
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Limpiar espacios en blanco y que la primera letra de cada palabra sea mayúscula
        $data['name'] = ucwords(trim($data['name']));
        return $data;
    }
}
