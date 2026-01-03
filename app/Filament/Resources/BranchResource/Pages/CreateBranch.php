<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateBranch extends CreateRecord
{
    protected static string $resource = BranchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
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

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Sucursal registrada exitosamente')
            ->body('La sucursal "' . $this->record->name . '" ha sido creada correctamente.')
            ->duration(5000)
            ->send();
    }
}
