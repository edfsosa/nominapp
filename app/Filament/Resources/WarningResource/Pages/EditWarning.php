<?php

namespace App\Filament\Resources\WarningResource\Pages;

use App\Filament\Resources\WarningResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Página de edición de una amonestación. */
class EditWarning extends EditRecord
{
    protected static string $resource = WarningResource::class;

    protected static ?string $title = 'Editar';

    /**
     * Define las acciones del encabezado de la página de edición.
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->modalHeading('Eliminar amonestación')
                ->modalDescription('¿Estás seguro de que deseas eliminar esta amonestación? Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->successNotificationTitle('Amonestación eliminada'),
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
     * Notificación de edición exitosa.
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Amonestación actualizada')
            ->body('Los cambios fueron guardados correctamente.');
    }
}
