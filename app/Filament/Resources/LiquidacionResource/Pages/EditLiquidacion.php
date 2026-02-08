<?php

namespace App\Filament\Resources\LiquidacionResource\Pages;

use App\Filament\Resources\LiquidacionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLiquidacion extends EditRecord
{
    protected static string $resource = LiquidacionResource::class;

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
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
