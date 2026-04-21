<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use Filament\Actions\Action;
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
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Activar')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->isPending())
                ->requiresConfirmation()
                ->modalHeading('Activar Préstamo')
                ->modalDescription(function () {
                    $payrollType = $this->record->employee->payroll_type_label;

                    return "Se generarán {$this->record->installments_count} cuotas. La primera se descontará en la próxima nómina ({$payrollType}).";
                })
                ->modalSubmitActionLabel('Sí, activar')
                ->action(function () {
                    $result = $this->record->activate(Auth::id());

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Préstamo Activado')
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
                ->visible(fn () => $this->record->isActive())
                ->requiresConfirmation()
                ->modalHeading('Marcar Préstamo como en Mora')
                ->modalDescription('El préstamo quedará en estado "En Mora". Las cuotas pendientes se conservan y podrán cobrarse una vez regularizado.')
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
                ->visible(fn () => $this->record->isDefaulted())
                ->requiresConfirmation()
                ->modalHeading('Reactivar Préstamo')
                ->modalDescription('El préstamo volverá a estado "Activo" y sus cuotas pendientes podrán cobrarse en la próxima nómina.')
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
                ->visible(fn () => $this->record->isPending() || $this->record->isActive() || $this->record->isDefaulted())
                ->requiresConfirmation()
                ->modalHeading('Cancelar Préstamo')
                ->modalDescription(function () {
                    if ($this->record->isActive() || $this->record->isDefaulted()) {
                        $pendingCount = $this->record->pending_installments_count;

                        return "¿Está seguro de que desea cancelar este préstamo? Se cancelarán {$pendingCount} cuota(s) pendiente(s).";
                    }

                    return '¿Está seguro de que desea cancelar este préstamo?';
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
                            ->title('Préstamo Cancelado')
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
                ->visible(fn () => $this->record->isActive() || $this->record->isPaid() || $this->record->isDefaulted())
                ->url(fn () => route('loans.pdf', $this->record))
                ->openUrlInNewTab(),

            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->visible(fn () => $this->record->isPending()),
        ];
    }
}
