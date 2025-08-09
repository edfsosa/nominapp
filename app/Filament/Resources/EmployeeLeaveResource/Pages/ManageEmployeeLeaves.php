<?php

namespace App\Filament\Resources\EmployeeLeaveResource\Pages;

use App\Filament\Resources\EmployeeLeaveResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeLeaves extends ManageRecords
{
    protected static string $resource = EmployeeLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
