<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewLoan extends ViewRecord
{
    protected static string $resource = LoanResource::class;

    /**
     * Define las acciones del encabezado de la página de detalle del préstamo.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Activar')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn() => $this->record->isPending())
                ->requiresConfirmation()
                ->modalHeading(fn() => "Activar {$this->record->type_label}")
                ->modalDescription(function () {
                    $amount      = number_format($this->record->installment_amount, 0, ',', '.');
                    $payrollType = $this->record->employee->payroll_type_label;

                    if ($this->record->isAdvance()) {
                        return "Se generará 1 cuota de {$amount} Gs. que se descontará automáticamente en la nómina actual ({$payrollType}).";
                    }

                    return "Se generarán {$this->record->installments_count} cuotas de {$amount} Gs. cada una. La primera cuota se descontará en la próxima nómina ({$payrollType}).";
                })
                ->modalSubmitActionLabel('Sí, activar')
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

            Action::make('mark_defaulted')
                ->label('Marcar en Mora')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->visible(fn() => $this->record->isActive())
                ->requiresConfirmation()
                ->modalHeading(fn() => "Marcar {$this->record->type_label} como en Mora")
                ->modalDescription('El préstamo/adelanto quedará en estado "En Mora". Las cuotas pendientes se conservan y podrán cobrarse una vez regularizado.')
                ->modalSubmitActionLabel('Sí, marcar en mora')
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->placeholder('Ingrese el motivo...')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $result = $this->record->markAsDefaulted($data['reason'] ?? null);

                    if ($result['success']) {
                        Notification::make()
                            ->warning()
                            ->title('Marcado en Mora')
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

            Action::make('reactivate')
                ->label('Reactivar')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn() => $this->record->isDefaulted())
                ->requiresConfirmation()
                ->modalHeading(fn() => "Reactivar {$this->record->type_label}")
                ->modalDescription('El préstamo/adelanto volverá a estado "Activo" y sus cuotas pendientes podrán cobrarse en la próxima nómina.')
                ->modalSubmitActionLabel('Sí, reactivar')
                ->action(function () {
                    $result = $this->record->reactivate();

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Reactivado')
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

            Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => $this->record->isPending() || $this->record->isActive() || $this->record->isDefaulted())
                ->requiresConfirmation()
                ->modalHeading(fn() => "Cancelar {$this->record->type_label}")
                ->modalDescription(function () {
                    $type = strtolower($this->record->type_label);

                    if ($this->record->isActive() || $this->record->isDefaulted()) {
                        $pendingCount  = $this->record->pending_installments_count;
                        $pendingAmount = $this->record->pending_amount;
                        return "¿Está seguro de que desea cancelar este {$type}? Se cancelarán {$pendingCount} cuota(s) pendiente(s) por un total de " . number_format($pendingAmount, 0, ',', '.') . " Gs.";
                    }

                    return "¿Está seguro de que desea cancelar este {$type}?";
                })
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

            Action::make('export_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->visible(fn() => $this->record->isActive() || $this->record->isPaid() || $this->record->isDefaulted())
                ->url(fn() => route('loans.pdf', $this->record))
                ->openUrlInNewTab(),

            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(fn() => $this->record->isPending()),

            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn() => $this->record->isPending() || $this->record->isCancelled()),
        ];
    }
}
