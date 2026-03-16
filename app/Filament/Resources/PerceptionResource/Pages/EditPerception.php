<?php

namespace App\Filament\Resources\PerceptionResource\Pages;

use App\Filament\Resources\PerceptionResource;
use App\Models\Perception;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPerception extends EditRecord
{
    protected static string $resource = PerceptionResource::class;

    /**
     * Obtiene las acciones que se mostrarán en el encabezado de la página de edición.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->modalHeading('¿Estás seguro de que deseas eliminar esta percepción?')
                ->modalDescription('Esta acción no se puede deshacer. Asegúrate de que no haya empleados asignados a esta percepción antes de eliminarla.')
                ->before(function (Perception $record, DeleteAction $action) {
                    $count = $record->activeEmployees()->count();

                    if ($count > 0) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede eliminar esta percepción')
                            ->body("La percepción \"{$record->name}\" tiene {$count} empleado(s) con asignación activa. Para eliminarla, primero debés remover todos los empleados desde la pestaña \"Empleados Asignados\" o usar la acción \"Remover de Todos\" en el listado.")
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    /**
     * Obtiene la URL a la que se redirigirá después de actualizar el registro.
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Obtiene el título de la notificación que se mostrará después de actualizar el registro.
     *
     * @return string|null
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Percepción actualizada exitosamente';
    }

    /**
     * Obtiene los administradores de relaciones que se mostrarán en la página de edición.
     *
     * @return array
     */
    public function getRelationManagers(): array
    {
        return [
            PerceptionResource\RelationManagers\EmployeePerceptionsRelationManager::class,
        ];
    }
}
