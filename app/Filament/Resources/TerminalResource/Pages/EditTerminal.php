<?php

namespace App\Filament\Resources\TerminalResource\Pages;

use App\Filament\Resources\TerminalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Edición de una terminal de marcación. */
class EditTerminal extends EditRecord
{
    protected static string $resource = TerminalResource::class;

    /**
     * Acciones del encabezado de la página de edición.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }

    /**
     * Redirige a la vista de detalle tras guardar.
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Notificación de éxito al guardar cambios.
     *
     * @return Notification|null
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Terminal actualizada');
    }
}
