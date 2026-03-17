<?php

namespace App\Filament\Resources\AguinaldoPeriodResource\Pages;

use App\Filament\Resources\AguinaldoPeriodResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAguinaldoPeriod extends EditRecord
{
    protected static string $resource = AguinaldoPeriodResource::class;

    /**
     * Sobre-escribe el método de autorización para permitir la edición solo si el período está en estado "borrador".
     *
     * @return void
     */
    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        if (! $this->record->isDraft()) {
            Notification::make()
                ->warning()
                ->title('Solo se pueden editar períodos en borrador')
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    /**
     * Define las acciones que se mostrarán en el encabezado de la página de edición.
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
                ->modalHeading('Eliminar Período de Aguinaldo')
                ->modalDescription('¿Estás seguro de que deseas eliminar este período de aguinaldo? Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->visible(fn() => $this->record->isDraft()),
        ];
    }

    /**
     * Define la URL a la que se redirigirá después de guardar los cambios.
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Obtiene el título de la notificación que se mostrará después de actualizar el registro.
     *
     * @return string|null
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Período de aguinaldo actualizado exitosamente';
    }
}
