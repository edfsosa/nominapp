<?php

namespace App\Filament\Resources\ShiftTemplateResource\Pages;

use App\Filament\Resources\ShiftTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Página de edición de turno. */
class EditShiftTemplate extends EditRecord
{
    protected static string $resource = ShiftTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Desactivar')
                ->icon('heroicon-o-archive-box')
                ->color('danger')
                ->modalHeading('Desactivar turno')
                ->modalDescription('Este turno puede estar en uso. ¿Deseas desactivarlo?')
                ->modalSubmitActionLabel('Sí, desactivar')
                ->action(fn() => $this->record->update(['is_active' => false]))
                ->successRedirectUrl(ShiftTemplateResource::getUrl('index')),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Turno actualizado')
            ->body('Los cambios fueron guardados correctamente.');
    }
}
