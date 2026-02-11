<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Resources\PayrollPeriodResource;
use App\Services\PayrollService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPayrollPeriod extends ViewRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_payrolls')
                ->label('Generar Recibos')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generar Recibos de Nómina')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de generar los recibos de nómina para el período {$this->record->name}? " .
                        "Esta acción creará recibos para todos los empleados activos."
                )
                ->action(function (PayrollService $payrollService) {
                    $count = $payrollService->generateForPeriod($this->record);

                    if ($count > 0) {
                        $this->record->update([
                            'status' => 'processing',
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Recibos generados')
                            ->body("Se generaron exitosamente {$count} recibos de nómina.")
                            ->send();

                        $this->refreshFormData([
                            'status',
                            'updated_at',
                        ]);
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('No se generaron recibos')
                            ->body('Es posible que ya hayan sido generados o que no haya empleados activos.')
                            ->send();
                    }
                })
                ->visible(fn() => in_array($this->record->status, ['draft', 'processing'])),

            Action::make('close_period')
                ->label('Cerrar Período')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cerrar Período de Nómina')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de cerrar el período {$this->record->name}? " .
                        "Una vez cerrado, no se podrán generar más recibos ni realizar modificaciones."
                )
                ->action(function () {
                    $this->record->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Período cerrado')
                        ->body("El período {$this->record->name} ha sido cerrado exitosamente.")
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'closed_at',
                        'updated_at',
                    ]);
                })
                ->visible(fn() => $this->record->status === 'processing'),

            Action::make('reopen_period')
                ->label('Reabrir Período')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reabrir Período de Nómina')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de reabrir el período {$this->record->name}? " .
                        "Esto permitirá realizar modificaciones nuevamente."
                )
                ->action(function () {
                    $this->record->update([
                        'status' => 'processing',
                        'closed_at' => null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Período reabierto')
                        ->body("El período {$this->record->name} ha sido reabierto exitosamente.")
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'closed_at',
                        'updated_at',
                    ]);
                })
                ->visible(fn() => $this->record->status === 'closed'),

            EditAction::make()
                ->visible(fn() => $this->record->status !== 'closed'),

            DeleteAction::make()
                ->visible(fn() => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Período de Nómina')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de eliminar el período {$this->record->name}? " .
                        "Esta acción no se puede deshacer."
                )
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Período eliminado')
                        ->body('El período ha sido eliminado correctamente.')
                ),
        ];
    }
}
