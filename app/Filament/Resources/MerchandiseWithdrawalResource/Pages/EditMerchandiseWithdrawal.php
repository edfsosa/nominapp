<?php

namespace App\Filament\Resources\MerchandiseWithdrawalResource\Pages;

use App\Filament\Resources\MerchandiseWithdrawalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Página de edición de retiro de mercadería (solo disponible en estado pendiente). */
class EditMerchandiseWithdrawal extends EditRecord
{
    protected static string $resource = MerchandiseWithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->modalHeading('Eliminar Retiro')
                ->modalDescription('¿Está seguro? Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->successNotificationTitle('Retiro eliminado'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Retiro actualizado')
            ->body('Los datos del retiro fueron guardados.');
    }
}
