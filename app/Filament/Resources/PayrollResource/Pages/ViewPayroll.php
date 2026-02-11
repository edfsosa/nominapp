<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
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
                ->openUrlInNewTab(),

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
                ->visible(fn() => $this->record->period?->status !== 'closed'),

            EditAction::make()
                ->visible(fn() => $this->record->period?->status !== 'closed'),

            DeleteAction::make()
                ->visible(fn() => $this->record->period?->status === 'draft')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Recibo eliminado')
                        ->body('El recibo ha sido eliminado correctamente.')
                ),
        ];
    }
}
