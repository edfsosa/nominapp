<?php

namespace App\Filament\Resources\RotationPatternResource\Pages;

use App\Filament\Resources\RotationPatternResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Página de edición de patrón de rotación. */
class EditRotationPattern extends EditRecord
{
    protected static string $resource = RotationPatternResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return RotationPatternResource::mutateFormDataBeforeFill($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Desactivar')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->modalHeading('Desactivar patrón')
                ->modalDescription('Los empleados con este patrón activo dejarán de tener turno calculado. ¿Continuar?')
                ->modalSubmitActionLabel('Sí, desactivar')
                ->action(fn() => $this->record->update(['is_active' => false]))
                ->successRedirectUrl(RotationPatternResource::getUrl('index')),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Patrón actualizado')
            ->body('Los cambios fueron guardados correctamente.');
    }
}
