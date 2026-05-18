<?php

namespace App\Filament\Resources\DisbursementBatchResource\Pages;

use App\Filament\Resources\DisbursementBatchResource;
use App\Models\CompanyBankAccount;
use App\Models\DisbursementBatch;
use App\Services\BankPaymentExportService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Página de detalle de un lote de pago bancario.
 *
 * Acciones disponibles según estado del lote:
 *  - pending: Descargar TXT, Confirmar Lote, Cancelar Lote.
 *  - confirmed / partially_confirmed / cancelled: sin acciones de mutación.
 */
class ViewDisbursementBatch extends ViewRecord
{
    protected static string $resource = DisbursementBatchResource::class;

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_txt')
                ->label('Descargar TXT')
                ->icon('heroicon-o-document-arrow-down')
                ->color('primary')
                ->visible(fn () => $this->record->isPending())
                ->modalHeading('Generar archivo TXT Itaú')
                ->modalDescription(function () {
                    $batch = $this->record;
                    $advances = $batch->advances()->where('status', 'approved');
                    $count = $advances->count();
                    $total = number_format((float) $advances->sum('amount'), 0, ',', '.');
                    $fecha = $batch->fecha_credito->format('d/m/Y');

                    return "Se generará el archivo para acreditación bancaria del {$fecha} con {$count} ".($count === 1 ? 'adelanto' : 'adelantos')." por un total de Gs. {$total}.";
                })
                ->modalSubmitActionLabel('Descargar')
                ->action(function (Action $action) {
                    /** @var DisbursementBatch $batch */
                    $batch = $this->record;

                    $account = CompanyBankAccount::where('company_id', $batch->company_id)
                        ->where('is_primary', true)
                        ->where('status', 'active')
                        ->first();

                    if (! $account) {
                        Notification::make()->danger()
                            ->title('Sin cuenta bancaria principal')
                            ->body('La empresa no tiene cuenta bancaria principal activa configurada.')
                            ->send();
                        $action->halt();

                        return;
                    }

                    if (! $account->bank_company_id) {
                        Notification::make()->danger()
                            ->title('ID Empresa no configurado')
                            ->body('Completá el ID Empresa en la cuenta bancaria principal antes de generar el TXT.')
                            ->send();
                        $action->halt();

                        return;
                    }

                    $advances = $batch->advances()
                        ->where('status', 'approved')
                        ->with(['employee.bankAccounts' => fn ($q) => $q->where('is_primary', true)->where('status', 'active')])
                        ->get();

                    if ($advances->isEmpty()) {
                        Notification::make()->warning()
                            ->title('Sin adelantos disponibles')
                            ->body('No hay adelantos aprobados en este lote para generar el TXT.')
                            ->send();
                        $action->halt();

                        return;
                    }

                    $params = [
                        'id_empresa' => $account->bank_company_id,
                        'cuenta_debito' => $account->account_number,
                        'moneda' => 'Guaraní',
                        'tipo' => 'Crédito',
                        'fecha_credito' => $batch->fecha_credito->format('Y-m-d'),
                    ];

                    $content = app(BankPaymentExportService::class)->generateTxt($params, $advances, stampDate: false);

                    // Guarda el TXT en storage y registra la ruta en el lote.
                    $filename = 'TRANSFER_'.$batch->id.'_'.now()->format('Y_m_d_H_i_s').'.txt';
                    $path = 'disbursement_batches/'.$filename;
                    Storage::disk('public')->put($path, $content);

                    $batch->update(['file_path' => $path]);

                    return response()->streamDownload(
                        fn () => print ($content),
                        $filename,
                        ['Content-Type' => 'text/plain; charset=UTF-8']
                    );
                }),

            Action::make('confirm_batch')
                ->label('Confirmar Lote')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->isPending())
                ->modalHeading('Confirmar resultado bancario')
                ->modalDescription(fn () => 'Adjuntá el comprobante del banco y marcá los adelantos rechazados desde la tabla antes de confirmar. Los adelantos no rechazados quedarán como Entregados.')
                ->modalSubmitActionLabel('Confirmar')
                ->form([
                    FileUpload::make('bank_confirmation_path')
                        ->label('Comprobante bancario')
                        ->disk('public')
                        ->directory('disbursement_batches/confirmations')
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(10240)
                        ->required()
                        ->getUploadedFileNameForStorageUsing(function ($file): string {
                            $ext = $file->getClientOriginalExtension();

                            return 'confirmacion_lote_'.$this->record->id.'_'.now()->format('Y-m-d_H-i-s').'.'.$ext;
                        })
                        ->helperText('Obligatorio. Formatos aceptados: PDF, JPG, PNG, WEBP. Tamaño máximo: 10 MB.'),
                ])
                ->action(function (array $data) {
                    $result = $this->record->confirm(
                        confirmedById: Auth::id(),
                        bankConfirmationPath: $data['bank_confirmation_path'],
                    );

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Lote confirmado')
                            ->body($result['message'])
                            ->send();

                        $this->refreshFormData(['status', 'confirmed_at', 'confirmed_by_id', 'bank_confirmation_path']);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['message'])
                            ->send();
                    }
                }),

            Action::make('cancel_batch')
                ->label('Cancelar Lote')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->isPending())
                ->requiresConfirmation()
                ->modalHeading('Cancelar lote de pago')
                ->modalDescription(fn () => 'Se cancelará el lote y los adelantos incluidos quedarán disponibles para ser asignados a otro lote. ¿Confirmar?')
                ->modalSubmitActionLabel('Sí, cancelar lote')
                ->action(function () {
                    $result = $this->record->cancel();

                    if ($result['success']) {
                        Notification::make()
                            ->warning()
                            ->title('Lote cancelado')
                            ->body($result['message'])
                            ->send();

                        $this->refreshFormData(['status']);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['message'])
                            ->send();
                    }
                }),
        ];
    }
}
