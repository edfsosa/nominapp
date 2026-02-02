<?php

namespace App\Filament\Resources\LoanResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Textarea;
use App\Filament\Resources\LoanResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;
    protected static ?string $title = 'Editar';

    /**
     * Define las acciones del encabezado de la página
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            /**
             * Acción para activar el préstamo o adelanto
             */
            Action::make('activate')
                ->label('Activar')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn() => $this->record->isPending())
                ->requiresConfirmation()
                ->modalHeading(fn() => "Activar {$this->record->type_label}")
                ->modalDescription(function () {
                    $amount = number_format($this->record->installment_amount, 0, ',', '.');
                    $payrollType = $this->record->employee->payroll_type_label;

                    if ($this->record->isAdvance()) {
                        return "Se generará 1 cuota de {$amount} Gs. que se descontará automáticamente en la próxima nómina ({$payrollType}).";
                    }

                    return "Se generarán {$this->record->installments_count} cuotas de {$amount} Gs. cada una. La primera cuota se descontará en la próxima nómina ({$payrollType}).";
                })
                ->action(function () {
                    $result = $this->record->activate(Auth::id());

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title("{$this->record->type_label} Activado")
                            ->body($result['message'])
                            ->send();

                        $this->refreshFormData(['status', 'granted_at', 'granted_by_id']);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['message'])
                            ->send();
                    }
                }),

            /**
             * Acción para cancelar el préstamo o adelanto
             */
            Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => $this->record->isPending() || ($this->record->isActive() && $this->record->paid_installments_count === 0))
                ->requiresConfirmation()
                ->modalHeading(fn() => "Cancelar {$this->record->type_label}")
                ->modalDescription(function () {
                    $type = strtolower($this->record->type_label);

                    if ($this->record->isActive()) {
                        $pendingCount = $this->record->pending_installments_count;
                        $pendingAmount = $this->record->pending_amount;
                        return "¿Está seguro de que desea cancelar este {$type}? Se cancelarán {$pendingCount} cuota(s) pendiente(s) por un total de " . number_format($pendingAmount, 0, ',', '.') . " Gs.";
                    }

                    return "¿Está seguro de que desea cancelar este {$type}?";
                })
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
                            ->title("{$this->record->type_label} Cancelado")
                            ->body($result['message'])
                            ->send();

                        $this->refreshFormData(['status', 'notes']);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['message'])
                            ->send();
                    }
                }),

            /**
             * Acción para exportar el préstamo o adelanto a PDF
             */
            Action::make('export_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url(fn() => route('loans.pdf', $this->record))
                ->openUrlInNewTab(),

            /**
             * Acción para eliminar el préstamo o adelanto
             */
            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn() => $this->record->isPending() || $this->record->isCancelled()),
        ];
    }

    /**
     * Título de la notificación al editar el préstamo
     *
     * @return string|null
     */
    protected function getEditedNotificationTitle(): ?string
    {
        return 'Préstamo actualizado exitosamente';
    }
}
