<?php

namespace App\Filament\Resources\TerminalResource\Pages;

use App\Filament\Resources\TerminalResource;
use App\Models\Terminal;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/** Vista de detalle de una terminal con QR de acceso y acciones de ciclo de vida. */
class ViewTerminal extends ViewRecord
{
    protected static string $resource = TerminalResource::class;

    /**
     * Acciones del encabezado de la página de detalle.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Activar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => $this->record->isInactive())
                ->requiresConfirmation()
                ->modalHeading('Activar terminal')
                ->modalDescription("La terminal volverá a estar disponible para marcaciones.")
                ->modalSubmitActionLabel('Sí, activar')
                ->action(function () {
                    $this->record->update(['status' => 'active']);
                    Notification::make()->success()->title('Terminal activada')->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('deactivate')
                ->label('Desactivar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => $this->record->isActive())
                ->requiresConfirmation()
                ->modalHeading('Desactivar terminal')
                ->modalDescription('La terminal dejará de aceptar marcaciones y mostrará una pantalla de fuera de servicio.')
                ->modalSubmitActionLabel('Sí, desactivar')
                ->action(function () {
                    $this->record->update(['status' => 'inactive']);
                    Notification::make()->warning()->title('Terminal desactivada')->send();
                    $this->refreshFormData(['status']);
                }),

            Action::make('regenerate_code')
                ->label('Regenerar código')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerar código de acceso')
                ->modalDescription('⚠️ Esto cambiará la URL de la terminal. El dispositivo físico dejará de funcionar hasta que sea reconfigurado con la nueva URL.')
                ->modalSubmitActionLabel('Sí, regenerar')
                ->action(function () {
                    $newCode = Terminal::generateUniqueCode();
                    $this->record->update(['code' => $newCode]);

                    Notification::make()
                        ->warning()
                        ->title('Código regenerado')
                        ->body('Recordá actualizar la URL en el dispositivo físico.')
                        ->send();

                    $this->refreshFormData(['code']);
                }),

            EditAction::make()->icon('heroicon-o-pencil-square'),

            DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }
}
