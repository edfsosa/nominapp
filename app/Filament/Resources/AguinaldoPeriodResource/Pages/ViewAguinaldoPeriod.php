<?php

namespace App\Filament\Resources\AguinaldoPeriodResource\Pages;

use App\Filament\Resources\AguinaldoPeriodResource;
use App\Services\AguinaldoService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAguinaldoPeriod extends ViewRecord
{
    protected static string $resource = AguinaldoPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_aguinaldos')
                ->label('Generar Aguinaldos')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generar Aguinaldos')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de generar los aguinaldos para {$this->record->company->name} - {$this->record->year}? " .
                        "Esta acción creará aguinaldos para todos los empleados que hayan tenido nóminas en el año."
                )
                ->action(function (AguinaldoService $aguinaldoService) {
                    $count = $aguinaldoService->generateForPeriod($this->record);

                    if ($count > 0) {
                        $this->record->update([
                            'status' => 'processing',
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Aguinaldos generados')
                            ->body("Se generaron exitosamente {$count} aguinaldos.")
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('No se generaron aguinaldos')
                            ->body('Es posible que ya hayan sido generados o que no haya nóminas para el año seleccionado.')
                            ->send();
                    }

                    $this->refreshFormData(['status']);
                })
                ->visible(fn() => $this->record->status === 'draft'),

            Action::make('close_period')
                ->label('Cerrar Período')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cerrar Período de Aguinaldo')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de cerrar el período de aguinaldo {$this->record->company->name} - {$this->record->year}? " .
                        "Una vez cerrado, no se podrán generar más aguinaldos para este período."
                )
                ->action(function () {
                    $this->record->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Período cerrado')
                        ->body("El período de aguinaldo {$this->record->year} ha sido cerrado exitosamente.")
                        ->send();

                    $this->refreshFormData(['status', 'closed_at']);
                })
                ->visible(fn() => $this->record->status === 'processing'),

            EditAction::make(),
        ];
    }
}
