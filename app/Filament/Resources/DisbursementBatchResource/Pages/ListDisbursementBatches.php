<?php

namespace App\Filament\Resources\DisbursementBatchResource\Pages;

use App\Filament\Resources\DisbursementBatchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDisbursementBatches extends ListRecords
{
    protected static string $resource = DisbursementBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
