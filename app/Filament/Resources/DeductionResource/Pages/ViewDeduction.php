<?php

namespace App\Filament\Resources\DeductionResource\Pages;

use App\Filament\Resources\DeductionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDeduction extends ViewRecord
{
    protected static string $resource = DeductionResource::class;

    /**
     * Obtiene las acciones que se mostrarán en el encabezado de la página de visualización.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square')
        ];
    }

    /**
     * Obtiene los gestores de relaciones que se mostrarán en la página de visualización.
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
