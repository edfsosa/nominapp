<?php

namespace App\Filament\Resources\AbsentResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\AbsentResource;

class ListAbsents extends ListRecords
{
    protected static string $resource = AbsentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar ausencia')
                ->icon('heroicon-o-plus'),
        ];
    }
}
