<?php

namespace App\Filament\Resources\ContractResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ContractResource;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    /**
     * Define las acciones del encabezado, incluyendo la acción para crear un nuevo contrato.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Contrato')
                ->icon('heroicon-o-plus'),
        ];
    }
}
