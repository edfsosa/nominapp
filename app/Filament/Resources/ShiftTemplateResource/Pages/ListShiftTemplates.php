<?php

namespace App\Filament\Resources\ShiftTemplateResource\Pages;

use App\Filament\Resources\ShiftTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/** Página de listado de turnos. */
class ListShiftTemplates extends ListRecords
{
    protected static string $resource = ShiftTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo Turno'),
        ];
    }
}
