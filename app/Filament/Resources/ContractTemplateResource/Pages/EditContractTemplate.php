<?php

namespace App\Filament\Resources\ContractTemplateResource\Pages;

use App\Filament\Resources\ContractTemplateResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Edición de la plantilla de cuerpo/cláusulas para un tipo de contrato. */
class EditContractTemplate extends EditRecord
{
    protected static string $resource = ContractTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ContractTemplateResource::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ContractTemplateResource::getUrl('index');
    }

    /**
     * Retorna la notificación de éxito al guardar la plantilla.
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Plantilla guardada')
            ->body('La plantilla de cláusulas fue actualizada correctamente.');
    }
}
