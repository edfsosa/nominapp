<?php

namespace App\Filament\Resources\CheckInResource\Pages;

use App\Filament\Resources\CheckInResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCheckIns extends ManageRecords
{
    protected static string $resource = CheckInResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
