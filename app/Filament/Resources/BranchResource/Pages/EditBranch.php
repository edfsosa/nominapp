<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Página de edición de una sucursal existente. */
class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

    /**
     * Retorna las acciones del encabezado: ver registro y eliminar.
     *
     * @return array<\Filament\Actions\Action>
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
                ->color('danger')
                ->modalHeading('¿Eliminar sucursal?')
                ->modalDescription(fn() => $this->record->employees()->count() > 0
                    ? "Esta sucursal tiene {$this->record->employees()->count()} empleado(s) asignado(s). Al eliminarla quedarán sin sucursal."
                    : "¿Estás seguro de que deseas eliminar la sucursal \"{$this->record->name}\"? Esta acción no se puede deshacer."
                )
                ->modalSubmitActionLabel('Sí, eliminar')
                ->successNotificationTitle('Sucursal eliminada')
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }

    /**
     * Capitaliza el nombre y limpia el teléfono antes de guardar.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['name'])) {
            $data['name'] = preg_replace_callback('/(?:^|\s)\S/u', fn($m) => mb_strtoupper($m[0], 'UTF-8'), $data['name']);
        }

        if (isset($data['phone'])) {
            $data['phone'] = preg_replace('/[\s\-]/', '', $data['phone']) ?: null;
        }

        return $data;
    }

    /**
     * Redirige a la página de detalle tras guardar los cambios.
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Notificación de éxito tras actualizar la sucursal.
     *
     * @return \Filament\Notifications\Notification|null
     */
    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Sucursal actualizada')
            ->body('Los datos de "' . $this->record->name . '" han sido actualizados correctamente.');
    }
}
