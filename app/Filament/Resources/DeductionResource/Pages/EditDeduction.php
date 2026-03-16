<?php

namespace App\Filament\Resources\DeductionResource\Pages;

use App\Filament\Resources\DeductionResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDeduction extends EditRecord
{
    protected static string $resource = DeductionResource::class;

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
                ->modalHeading('¿Estás seguro de que deseas eliminar esta deducción?')
                ->modalDescription('Esta acción no se puede deshacer. Asegúrate de que no haya empleados asignados a esta deducción antes de eliminarla.')
                ->before(function ($record, DeleteAction $action) {
                    $count = $record->activeEmployees()->count();

                    if ($count > 0) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede eliminar esta deducción')
                            ->body("La deducción \"{$record->name}\" tiene {$count} empleado(s) con asignación activa. Para eliminarla, primero debés remover todos los empleados desde la pestaña \"Empleados Asignados\" o usar la acción \"Remover de Todos\" en el listado.")
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
        return 'Deducción actualizada exitosamente';
    }

    /**
     * Obtiene los gestores de relaciones que se mostrarán en la página de edición.
     *
     * @return array
     */
    public function getRelationManagers(): array
    {
        return [
            DeductionResource\RelationManagers\EmployeeDeductionsRelationManager::class,
        ];
    }
}
