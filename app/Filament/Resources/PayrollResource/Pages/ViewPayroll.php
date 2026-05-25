<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use App\Models\Payroll;
use App\Services\PayrollService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewPayroll extends ViewRecord
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->url(fn () => route('payrolls.download', $this->record))
                ->openUrlInNewTab(),

            Action::make('approve')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar Recibo')
                ->modalDescription(fn () => "¿Está seguro de aprobar el recibo de {$this->record->employee->full_name} por ".Payroll::formatCurrency($this->record->net_salary).'?')
                ->modalSubmitActionLabel('Sí, aprobar')
                ->action(function () {
                    $this->record->update([
                        'status' => 'approved',
                        'approved_by_id' => Auth::id(),
                        'approved_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Recibo aprobado')
                        ->body("El recibo de {$this->record->employee->full_name} ha sido aprobado.")
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn () => $this->record->status === 'draft'),

            Action::make('mark_disbursed')
                ->label('Marcar Acreditado')
                ->icon('heroicon-o-building-library')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Marcar como Acreditado')
                ->modalDescription(fn () => "¿Confirma que el pago de {$this->record->employee->full_name} fue acreditado o entregado?")
                ->modalSubmitActionLabel('Sí, acreditar')
                ->action(function () {
                    $result = $this->record->markAsDisbursed(Auth::id());

                    Notification::make()
                        ->{$result['success'] ? 'success' : 'danger'}()
                        ->title($result['success'] ? 'Recibo acreditado' : 'Error')
                        ->body($result['message'])
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn () => $this->record->isApproved()),

            Action::make('mark_paid')
                ->label('Marcar Pagado')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Marcar como Pagado')
                ->modalDescription(fn () => "¿Confirma que el banco procesó el pago de {$this->record->employee->full_name}?")
                ->modalSubmitActionLabel('Sí, marcar como pagado')
                ->action(function () {
                    $this->record->update(['status' => 'paid']);

                    Notification::make()
                        ->success()
                        ->title('Recibo marcado como pagado')
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn () => $this->record->isDisbursed()),

            Action::make('revert_paid')
                ->label('Revertir Pago')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Revertir Pago')
                ->modalDescription(fn () => "¿Está seguro de revertir el pago del recibo de {$this->record->employee->full_name}? Volverá a estado Acreditado.")
                ->modalSubmitActionLabel('Sí, revertir')
                ->action(function () {
                    $this->record->update(['status' => 'disbursed']);

                    Notification::make()
                        ->success()
                        ->title('Pago revertido')
                        ->body('El recibo ha vuelto a estado Acreditado.')
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn () => $this->record->isPaid()),

            Action::make('revert_to_approved')
                ->label('Revertir a Aprobado')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Revertir a Aprobado')
                ->modalDescription(fn () => "¿Está seguro de revertir el recibo de {$this->record->employee->full_name} a estado Aprobado?")
                ->modalSubmitActionLabel('Sí, revertir')
                ->action(function () {
                    $result = $this->record->revertToApproved();

                    Notification::make()
                        ->{$result['success'] ? 'success' : 'danger'}()
                        ->title($result['success'] ? 'Recibo revertido' : 'No se pudo revertir')
                        ->body($result['message'])
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn () => $this->record->isDisbursed() && $this->record->disbursement_batch_id === null),

            Action::make('unapprove')
                ->label('Desaprobar')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Desaprobar Recibo')
                ->modalDescription(fn () => "¿Está seguro de desaprobar el recibo de {$this->record->employee->full_name}? Volverá a estado Borrador.")
                ->modalSubmitActionLabel('Sí, desaprobar')
                ->action(function () {
                    $this->record->update([
                        'status' => 'draft',
                        'approved_by_id' => null,
                        'approved_at' => null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Recibo desaprobado')
                        ->body('El recibo ha vuelto a estado Borrador.')
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn () => $this->record->isApproved()),

            Action::make('regenerate')
                ->label('Regenerar')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerar Recibo')
                ->modalDescription(fn () => 'Se recalcularán todos los ítems de este recibo. Esta acción reemplazará los valores actuales.')
                ->modalSubmitActionLabel('Sí, regenerar')
                ->action(function (PayrollService $payrollService) {
                    try {
                        $payrollService->regenerateForEmployee($this->record);

                        Notification::make()
                            ->success()
                            ->title('Recibo regenerado')
                            ->body('El recibo ha sido recalculado exitosamente.')
                            ->send();

                        $this->refreshFormData([
                            'base_salary',
                            'total_perceptions',
                            'total_deductions',
                            'gross_salary',
                            'net_salary',
                            'generated_at',
                            'pdf_path',
                        ]);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Error al regenerar')
                            ->body('Ocurrió un error al regenerar el recibo: '.$e->getMessage())
                            ->send();
                    }
                })
                ->visible(fn () => $this->record->status === 'draft'),

            DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Recibo eliminado')
                        ->body('El recibo ha sido eliminado correctamente.')
                ),
        ];
    }
}
