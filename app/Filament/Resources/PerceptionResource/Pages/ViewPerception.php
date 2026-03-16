<?php

namespace App\Filament\Resources\PerceptionResource\Pages;

use App\Filament\Resources\PerceptionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerception extends ViewRecord
{
    protected static string $resource = PerceptionResource::class;

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
            PerceptionResource\RelationManagers\EmployeePerceptionsRelationManager::class,
        ];
    }
}
