<?php

namespace App\Filament\Resources\DisbursementBatchResource\Pages;

use App\Filament\Resources\DisbursementBatchResource;
use App\Models\Advance;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

/**
 * Página de creación de un lote de pago bancario.
 *
 * Inyecta created_by_id y type, y vincula los adelantos seleccionados al lote
 * tras la creación.
 */
class CreateDisbursementBatch extends CreateRecord
{
    protected static string $resource = DisbursementBatchResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = Auth::id();
        $data['type'] = 'advances';

        return $data;
    }

    protected function afterCreate(): void
    {
        $advanceIds = $this->data['advance_ids'] ?? [];

        if (empty($advanceIds)) {
            return;
        }

        $updated = Advance::whereIn('id', $advanceIds)
            ->where('status', 'approved')
            ->where('payment_method', 'transfer')
            ->whereNull('disbursement_batch_id')
            ->update([
                'disbursement_batch_id' => $this->record->id,
                'bank_rejection_reason' => null,
            ]);

        if ($updated > 0) {
            Notification::make()
                ->success()
                ->title('Lote creado')
                ->body("Se vincularon {$updated} adelantos al lote. Descargá el TXT desde la vista del lote.")
                ->send();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null; // La notificación se envía en afterCreate() con más contexto.
    }
}
