<?php

namespace App\Filament\Resources\LiquidacionResource\Pages;

use App\Filament\Resources\LiquidacionResource;
use App\Models\Liquidacion;
use App\Services\LiquidacionService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewLiquidacion extends ViewRecord
{
    protected static string $resource = LiquidacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calculate')
                ->label('Calcular Liquidación')
                ->icon('heroicon-o-calculator')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Calcular Liquidación')
                ->modalDescription(
                    fn() => "¿Calcular la liquidación de {$this->record->employee->full_name}? " .
                        "Tipo: " . Liquidacion::getTerminationTypeLabel($this->record->termination_type)
                )
                ->action(function (LiquidacionService $service) {
                    try {
                        $service->calculate($this->record);

                        Notification::make()
                            ->success()
                            ->title('Liquidación calculada')
                            ->body("Neto a pagar: {$this->record->fresh()->formatted_net_amount}")
                            ->send();

                        $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->danger()
                            ->title('Error al calcular')
                            ->body($e->getMessage())
                            ->send();
                    }
                })
                ->visible(fn() => $this->record->isDraft()),

            Action::make('close')
                ->label('Cerrar y Desactivar Empleado')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cerrar Liquidación')
                ->modalDescription(
                    fn() => "¿Cerrar la liquidación de {$this->record->employee->full_name}? " .
                        "El empleado será marcado como INACTIVO y los préstamos pendientes serán cancelados."
                )
                ->action(function (LiquidacionService $service) {
                    $service->close($this->record);

                    Notification::make()
                        ->success()
                        ->title('Liquidación cerrada')
                        ->body("El empleado {$this->record->employee->full_name} ha sido desactivado.")
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn() => $this->record->isCalculated()),

            Action::make('download_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->url(fn() => route('liquidaciones.download', $this->record))
                ->openUrlInNewTab()
                ->visible(fn() => $this->record->pdf_path !== null),

            EditAction::make()
                ->visible(fn() => $this->record->isDraft()),
        ];
    }
}
