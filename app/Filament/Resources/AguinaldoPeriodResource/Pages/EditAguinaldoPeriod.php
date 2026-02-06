<?php

namespace App\Filament\Resources\AguinaldoPeriodResource\Pages;

use App\Filament\Resources\AguinaldoPeriodResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAguinaldoPeriod extends EditRecord
{
    protected static string $resource = AguinaldoPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn() => $this->record->status === 'draft'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
