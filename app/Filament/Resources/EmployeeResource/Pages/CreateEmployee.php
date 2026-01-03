<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convertir nombres y apellidos a mayúsculas
        if (isset($data['first_name'])) {
            $data['first_name'] = mb_strtoupper($data['first_name'], 'UTF-8');
        }

        if (isset($data['last_name'])) {
            $data['last_name'] = mb_strtoupper($data['last_name'], 'UTF-8');
        }

        // Convertir email a minúsculas y limpiar espacios
        if (isset($data['email'])) {
            $data['email'] = strtolower(trim($data['email']));
        }

        // Limpiar CI: eliminar ceros a la izquierda y espacios
        if (isset($data['ci'])) {
            $data['ci'] = ltrim(str_replace(' ', '', $data['ci']), '0') ?: '0';
        }

        // Limpiar teléfono: eliminar espacios, guiones y ceros a la izquierda
        if (isset($data['phone'])) {
            $cleaned = str_replace([' ', '-', '+595'], '', $data['phone']);
            $data['phone'] = ltrim($cleaned, '0') ?: null;
        }

        // Asegurar que solo se guarde el campo correcto según el tipo de empleo
        if (isset($data['employment_type'])) {
            if ($data['employment_type'] === 'full_time') {
                // Si es tiempo completo, asegurar que daily_rate sea null
                $data['daily_rate'] = null;

                // Limpiar y validar base_salary
                if (isset($data['base_salary'])) {
                    $data['base_salary'] = (float) $data['base_salary'] ?: null;
                }
            } else {
                // Si es jornalero, asegurar que base_salary sea null
                $data['base_salary'] = null;

                // Limpiar y validar daily_rate
                if (isset($data['daily_rate'])) {
                    $data['daily_rate'] = (float) $data['daily_rate'] ?: null;
                }
            }
        }

        // Asegurar que face_descriptor sea null en creación
        $data['face_descriptor'] = null;

        return $data;
    }

    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->success()
            ->title('Empleado registrado exitosamente')
            ->body('El empleado ' . $this->record->first_name . ' ' . $this->record->last_name . ' ha sido creado correctamente.')
            ->duration(5000)
            ->send();
    }
}
