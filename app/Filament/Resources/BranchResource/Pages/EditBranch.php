<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBranch extends EditRecord
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Eliminar')
                ->modalHeading('Eliminar sucursal')
                ->modalDescription(
                    fn() =>
                    $this->record->employees()->count() > 0
                        ? "Esta sucursal tiene {$this->record->employees()->count()} empleado(s) asignado(s). Al eliminarla, los empleados quedarán sin sucursal asignada."
                        : '¿Estás seguro de que deseas eliminar esta sucursal? Esta acción no se puede deshacer.'
                )
                ->successNotificationTitle('Sucursal eliminada')
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convertir nombre de sucursal a mayúsculas
        if (isset($data['name'])) {
            $data['name'] = mb_strtoupper($data['name'], 'UTF-8');
        }

        // Convertir ciudad a formato título (Primera Letra Mayúscula)
        if (isset($data['city'])) {
            $data['city'] = mb_convert_case($data['city'], MB_CASE_TITLE, 'UTF-8');
        }

        // Limpiar y formatear dirección
        if (isset($data['address'])) {
            $data['address'] = trim($data['address']);
        }

        // Convertir email a minúsculas y limpiar espacios
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        // Limpiar teléfono: eliminar espacios, guiones y ceros a la izquierda
        if (isset($data['phone'])) {
            $cleaned = str_replace([' ', '-', '+595'], '', $data['phone']);
            $data['phone'] = ltrim($cleaned, '0') ?: null;
        }

        // Procesar coordenadas
        if (isset($data['coordinates'])) {
            // Si ambas coordenadas están vacías, establecer coordinates como null
            if (empty($data['coordinates']['lat']) && empty($data['coordinates']['lng'])) {
                $data['coordinates'] = null;
            } else {
                // Convertir a float para precisión
                if (isset($data['coordinates']['lat'])) {
                    $data['coordinates']['lat'] = (float) $data['coordinates']['lat'];
                }
                if (isset($data['coordinates']['lng'])) {
                    $data['coordinates']['lng'] = (float) $data['coordinates']['lng'];
                }
            }
        }

        return $data;
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Sucursal actualizada exitosamente')
            ->body('Los datos de "' . $this->record->name . '" han sido actualizados.')
            ->duration(5000)
            ->send();
    }
}
