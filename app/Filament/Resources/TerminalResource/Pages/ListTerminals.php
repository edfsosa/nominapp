<?php

namespace App\Filament\Resources\TerminalResource\Pages;

use App\Filament\Resources\TerminalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Lista de terminales de marcación. */
class ListTerminals extends ListRecords
{
    protected static string $resource = TerminalResource::class;

    /**
     * Acciones del encabezado de la página.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Terminal')
                ->icon('heroicon-o-plus'),
        ];
    }
}
