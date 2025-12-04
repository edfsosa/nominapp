<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_profile')
                ->label('Ver perfil completo')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->url(fn() => $this->getResource()::getUrl('view', ['record' => $this->record]))
                ->visible(fn() => $this->getResource()::hasPage('view')),

            Action::make('capture_face')
                ->label(fn() => $this->record->face_descriptor ? 'Actualizar rostro' : 'Capturar rostro')
                ->icon('heroicon-o-camera')
                ->color(fn() => $this->record->face_descriptor ? 'warning' : 'success')
                ->url(fn() => route('face.capture', $this->record))
                ->visible(fn() => $this->record->status === 'active'),

            DeleteAction::make()
                ->label('Eliminar')
                ->modalHeading('Eliminar empleado')
                ->modalDescription('¿Estás seguro de que deseas eliminar este empleado? Esta acción no se puede deshacer.')
                ->successNotificationTitle('Empleado eliminado')
                ->before(function () {
                    // Eliminar la foto si existe
                    if ($this->record->photo && Storage::disk('public')->exists($this->record->photo)) {
                        Storage::disk('public')->delete($this->record->photo);
                    }
                })
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
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

        // Preservar el face_descriptor existente (no debe modificarse desde el formulario)
        $data['face_descriptor'] = $this->record->face_descriptor;

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Al cargar el formulario, asegurar que se muestre el campo correcto
        // según el tipo de empleo actual
        if (isset($data['employment_type'])) {
            if ($data['employment_type'] === 'full_time') {
                $data['daily_rate'] = null;
            } else {
                $data['base_salary'] = null;
            }
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Redirigir a la misma página de edición después de guardar
        // Esto mantiene al usuario en contexto
        $this->redirect($this->getResource()::getUrl('edit', [
            'record' => $this->record,
        ]));
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Empleado actualizado exitosamente';
    }

    protected function getSavedNotification(): ?\Filament\Notifications\Notification
    {
        return Notification::make()
            ->success()
            ->title('Empleado actualizado exitosamente')
            ->body('Los datos de ' . $this->record->first_name . ' ' . $this->record->last_name . ' han sido actualizados.')
            ->duration(5000)
            ->send();
    }

    // Personalizar el título de la página
    public function getTitle(): string
    {
        return 'Editar empleado: ' . $this->record->first_name . ' ' . $this->record->last_name;
    }

    // Personalizar el breadcrumb
    public function getBreadcrumb(): string
    {
        return 'Editar';
    }

    // Prevenir la navegación accidental
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Guardar cambios'),

            $this->getCancelFormAction()
                ->label('Cancelar')
                ->color('gray'),
        ];
    }
}
