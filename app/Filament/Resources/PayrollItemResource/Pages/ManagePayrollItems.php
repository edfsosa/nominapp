<?php

namespace App\Filament\Resources\PayrollItemResource\Pages;

use App\Filament\Resources\PayrollItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePayrollItems extends ManageRecords
{
    protected static string $resource = PayrollItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
