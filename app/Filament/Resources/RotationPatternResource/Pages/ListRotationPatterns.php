<?php

namespace App\Filament\Resources\RotationPatternResource\Pages;

use App\Filament\Resources\RotationPatternResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Página de listado de patrones de rotación. */
class ListRotationPatterns extends ListRecords
{
    protected static string $resource = RotationPatternResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo Patrón'),
        ];
    }
}
