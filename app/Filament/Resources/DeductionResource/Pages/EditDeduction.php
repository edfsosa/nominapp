<?php

namespace App\Filament\Resources\DeductionResource\Pages;

use App\Filament\Resources\DeductionResource;
use App\Models\Deduction;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/** Página de edición de una deducción. */
class EditDeduction extends EditRecord
{
    protected static string $resource = DeductionResource::class;

    /**
     * Acciones del encabezado: ver y eliminar.
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver')
                ->icon('heroicon-o-eye')
                ->color('gray'),

            DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->modalHeading('¿Eliminar deducción?')
                ->modalDescription('Esta acción no se puede deshacer. Asegurate de que no haya empleados asignados antes de eliminarla.')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->before(function (Deduction $record, DeleteAction $action) {
                    $count = $record->activeEmployees()->count();

                    if ($count > 0) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede eliminar esta deducción')
                            ->body("La deducción \"{$record->name}\" tiene {$count} empleado(s) con asignación activa. Primero remové todos los empleados desde \"Empleados Asignados\" o usá \"Remover de Todos\" en el listado.")
                            ->persistent()
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    /**
     * Redirige al view del record tras guardar.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Deducción actualizada')
            ->body('Los cambios fueron guardados exitosamente.');
    }

    /**
     * @return array<int, mixed>
     */
    public function getRelationManagers(): array
    {
        return [
            DeductionResource\RelationManagers\EmployeeDeductionsRelationManager::class,
        ];
    }
}
