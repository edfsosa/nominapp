<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\EmployeeResource;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Define las acciones del encabezado de la página de edición.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('capture_face')
                ->label(fn() => $this->record->has_face ? 'Actualizar rostro' : 'Capturar rostro')
                ->icon('heroicon-o-camera')
                ->color(fn() => $this->record->has_face ? 'warning' : 'success')
                ->url(fn() => route('face.capture', $this->record))
                ->visible(fn() => $this->record->status === 'active'),

            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->before(function () {
                    // Eliminar la foto si existe
                    if ($this->record->photo && Storage::disk('public')->exists($this->record->photo)) {
                        Storage::disk('public')->delete($this->record->photo);
                    }
                })
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }

    /**
     * Modifica los datos del formulario antes de guardarlos.
     *
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Sanitizar datos usando el método del modelo
        $data = Employee::sanitizeFormData($data, isCreating: false);

        // Preservar el face_descriptor existente (no debe modificarse desde el formulario)
        $data['face_descriptor'] = $this->record->face_descriptor;

        return $data;
    }

    /**
     * Modifica los datos del formulario antes de mostrarlos.
     *
     * @param array $data
     * @return array
     */
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

    /**
     * Personaliza la notificación que se muestra después de guardar.
     *
     * @return Notification
     */
    protected function getSavedNotification(): Notification
    {
        return Notification::make()
            ->success()
            ->title('Empleado actualizado exitosamente')
            ->body('Los datos de ' . $this->record->full_name . ' han sido actualizados.')
            ->duration(5000)
            ->send();
    }
}
