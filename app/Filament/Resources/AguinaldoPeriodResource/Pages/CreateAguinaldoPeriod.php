<?php

namespace App\Filament\Resources\AguinaldoPeriodResource\Pages;

use App\Filament\Resources\AguinaldoPeriodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAguinaldoPeriod extends CreateRecord
{
    protected static string $resource = AguinaldoPeriodResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
