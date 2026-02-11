<?php

namespace App\Filament\Resources\DeductionResource\Pages;

use App\Filament\Resources\DeductionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDeductions extends ListRecords
{
    protected static string $resource = DeductionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Deducción')
                ->icon('heroicon-o-plus')
                ->successNotificationTitle('Deducción creada exitosamente'),
        ];
    }
}
