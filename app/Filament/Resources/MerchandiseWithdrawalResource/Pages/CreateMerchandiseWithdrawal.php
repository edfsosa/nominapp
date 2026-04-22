<?php

namespace App\Filament\Resources\MerchandiseWithdrawalResource\Pages;

use App\Filament\Resources\MerchandiseWithdrawalResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

/** Página de creación de retiro de mercadería. */
class CreateMerchandiseWithdrawal extends CreateRecord
{
    protected static string $resource = MerchandiseWithdrawalResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Retiro registrado')
            ->body('El retiro de mercadería fue registrado en estado Pendiente. Agregue los productos y luego apruébelo.');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
