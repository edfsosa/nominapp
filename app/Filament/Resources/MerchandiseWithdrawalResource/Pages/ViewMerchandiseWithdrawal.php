<?php

namespace App\Filament\Resources\MerchandiseWithdrawalResource\Pages;

use App\Filament\Resources\MerchandiseWithdrawalResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

/** Página de detalle de un retiro de mercadería. */
class ViewMerchandiseWithdrawal extends ViewRecord
{
    protected static string $resource = MerchandiseWithdrawalResource::class;

    /**
     * Acciones del encabezado según el estado del retiro.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->isPending())
                ->requiresConfirmation()
                ->modalHeading('Aprobar Retiro')
                ->modalDescription(function () {
                    $days = $this->record->first_installment_days;
                    $firstDue = now()->addDays($days)->format('d/m/Y');
                    $count = $this->record->installments_count;

                    return "Se generarán {$count} cuota(s). La primera vencerá el {$firstDue} ({$days} días desde hoy).";
                })
                ->modalSubmitActionLabel('Sí, aprobar')
                ->action(function () {
                    $result = $this->record->approve(Auth::id());

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Retiro Aprobado')
                            ->body($result['message'])
                            ->send();

                        $this->redirect($this->getUrl(['record' => $this->record]));
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['message'])
                            ->send();
                    }
                }),

            Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-minus-circle')
                ->color('danger')
                ->visible(fn () => $this->record->isPending() || $this->record->isApproved())
                ->requiresConfirmation()
                ->modalHeading('Cancelar Retiro')
                ->modalDescription(fn () => $this->record->isApproved()
                    ? '¿Está seguro de que desea cancelar el retiro? Se cancelarán '.$this->record->pending_installments_count.' cuota(s) pendiente(s).'
                    : '¿Está seguro de que desea cancelar el retiro?'
                )
                ->modalSubmitActionLabel('Sí, cancelar')
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo de cancelación')
                        ->placeholder('Ingrese el motivo...')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $result = $this->record->cancel($data['reason'] ?? null);

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Retiro Cancelado')
                            ->body($result['message'])
                            ->send();

                        $this->redirect($this->getUrl(['record' => $this->record]));
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['message'])
                            ->send();
                    }
                }),

            Action::make('export_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->visible(fn () => $this->record->isApproved() || $this->record->isPaid())
                ->url(fn () => route('merchandise-withdrawals.pdf', $this->record))
                ->openUrlInNewTab(),

            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => $this->record->isPending()),
        ];
    }
}
