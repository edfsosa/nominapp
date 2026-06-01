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
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Ver')->icon('heroicon-o-eye')->color('gray'),
            DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->modalHeading('¿Eliminar terminal?')
                ->modalSubmitActionLabel('Sí, eliminar'),
        ];
    }

    /**
     * Redirige a la vista de detalle tras guardar.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Notificación de éxito al guardar cambios.
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Terminal actualizada');
    }
}
