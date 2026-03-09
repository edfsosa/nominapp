<?php

namespace App\Filament\Resources\LiquidacionResource\Pages;

use App\Filament\Resources\LiquidacionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLiquidacion extends EditRecord
{
    protected static string $resource = LiquidacionResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->isClosed()) {
            Notification::make()
                ->warning()
                ->title('Liquidación cerrada')
                ->body('No se puede editar una liquidación cerrada.')
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn() => $this->record->isDraft()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
