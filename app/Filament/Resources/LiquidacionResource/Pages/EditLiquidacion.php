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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!$this->record->isCalculated()) {
            return $data;
        }

        $calculationFieldsChanged =
            $data['termination_type'] !== $this->record->termination_type ||
            (bool) ($data['preaviso_otorgado'] ?? false) !== (bool) $this->record->preaviso_otorgado;

        if ($calculationFieldsChanged) {
            $this->record->items()->delete();
            $data['status']       = 'draft';
            $data['pdf_path']     = null;
            $data['calculated_at'] = null;

            Notification::make()
                ->warning()
                ->title('Liquidación revertida a borrador')
                ->body('Se modificaron parámetros de cálculo. La liquidación debe ser recalculada.')
                ->send();
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->visible(fn() => !$this->record->isClosed()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
