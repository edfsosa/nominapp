<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use App\Models\Payroll;
use App\Services\PayrollService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

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
                ->url(fn() => route('payrolls.download', $this->record))
                ->openUrlInNewTab()
                ->visible(fn() => $this->record->pdf_path),

            Action::make('approve')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar Recibo')
                ->modalDescription(fn() => "¿Está seguro de aprobar el recibo de {$this->record->employee->full_name} por " . Payroll::formatCurrency($this->record->net_salary) . "?")
                ->action(function () {
                    $this->record->update([
                        'status' => 'approved',
                        'approved_by_id' => auth()->id(),
                        'approved_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Recibo aprobado')
                        ->body("El recibo de {$this->record->employee->full_name} ha sido aprobado.")
                        ->send();

                    $this->refreshFormData(['status', 'approved_by_id', 'approved_at']);
                })
                ->visible(fn() => $this->record->status === 'draft'),

            Action::make('mark_paid')
                ->label('Marcar Pagado')
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Marcar como Pagado')
                ->modalDescription(fn() => "¿Confirma que el recibo de {$this->record->employee->full_name} ha sido pagado?")
                ->action(function () {
                    $this->record->update(['status' => 'paid']);

                    Notification::make()
                        ->success()
                        ->title('Recibo marcado como pagado')
                        ->send();

                    $this->refreshFormData(['status']);
                })
                ->visible(fn() => $this->record->status === 'approved'),

            Action::make('revert_paid')
                ->label('Revertir Pago')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Revertir Pago')
                ->modalDescription(fn() => "¿Está seguro de revertir el pago del recibo de {$this->record->employee->full_name}? Volverá a estado Aprobado.")
                ->action(function () {
                    $this->record->update(['status' => 'approved']);

                    Notification::make()
                        ->success()
                        ->title('Pago revertido')
                        ->body("El recibo ha vuelto a estado Aprobado.")
                        ->send();

                    $this->refreshFormData(['status']);
                })
                ->visible(fn() => $this->record->status === 'paid'),

            Action::make('unapprove')
                ->label('Desaprobar')
                ->icon('heroicon-o-x-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Desaprobar Recibo')
                ->modalDescription(fn() => "¿Está seguro de desaprobar el recibo de {$this->record->employee->full_name}? Volverá a estado Borrador.")
                ->action(function () {
                    $this->record->update([
                        'status' => 'draft',
                        'approved_by_id' => null,
                        'approved_at' => null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Recibo desaprobado')
                        ->body("El recibo ha vuelto a estado Borrador.")
                        ->send();

                    $this->refreshFormData(['status', 'approved_by_id', 'approved_at']);
                })
                ->visible(fn() => $this->record->status === 'approved'),

            Action::make('regenerate')
                ->label('Regenerar')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerar Recibo')
                ->modalDescription(fn() => "Se recalcularán todos los ítems de este recibo. Esta acción reemplazará los valores actuales.")
                ->action(function (PayrollService $payrollService) {
                    try {
                        $payrollService->regenerateForEmployee($this->record);

                        Notification::make()
                            ->success()
                            ->title('Recibo regenerado')
                            ->body("El recibo ha sido recalculado exitosamente.")
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
                            ->body('Ocurrió un error al regenerar el recibo: ' . $e->getMessage())
                            ->send();
                    }
                })
                ->visible(fn() => $this->record->status === 'draft'),

            EditAction::make()
                ->visible(fn() => $this->record->status === 'draft'),

            DeleteAction::make()
                ->visible(fn() => $this->record->status === 'draft')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Recibo eliminado')
                        ->body('El recibo ha sido eliminado correctamente.')
                ),
        ];
    }
}
