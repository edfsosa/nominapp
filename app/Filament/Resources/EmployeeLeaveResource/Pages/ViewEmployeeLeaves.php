<?php

namespace App\Filament\Resources\EmployeeLeaveResource\Pages;

use App\Filament\Resources\EmployeeLeaveResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeLeave extends ViewRecord
{
    protected static string $resource = EmployeeLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}