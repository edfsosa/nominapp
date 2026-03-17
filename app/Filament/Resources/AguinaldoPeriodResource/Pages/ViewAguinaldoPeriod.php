<?php

namespace App\Filament\Resources\AguinaldoPeriodResource\Pages;

use App\Exports\AguinaldosExport;
use App\Filament\Resources\AguinaldoPeriodResource;
use App\Services\AguinaldoService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Maatwebsite\Excel\Facades\Excel;

class ViewAguinaldoPeriod extends ViewRecord
{
    protected static string $resource = AguinaldoPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_aguinaldos')
                ->label('Generar')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading("¿Generar los aguinaldos?")
                ->modalDescription(
                    fn() => "Esta acción generará los aguinaldos correspondientes al período de {$this->record->year} para la empresa {$this->record->company->name}. Si ya existen aguinaldos generados para este período, no se generarán duplicados."
                )
                ->modalSubmitActionLabel('Sí, generar')
                ->action(function (AguinaldoService $aguinaldoService) {
                    $count = $aguinaldoService->generateForPeriod($this->record);

                    if ($count > 0) {
                        $this->record->update(['status' => 'processing']);

                        Notification::make()
                            ->success()
                            ->title('Aguinaldos generados')
                            ->body("Se generaron exitosamente {$count} aguinaldos para el período {$this->record->year}.")
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('No se generaron aguinaldos')
                            ->body("Ya fueron generados o no hay nóminas para el período {$this->record->year} de {$this->record->company->name}.")
                            ->send();
                    }

                    $this->refreshFormData(['status']);
                })
                ->visible(fn() => $this->record->isDraft()),

            Action::make('mark_all_paid')
                ->label('Pagar Todos')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Marcar todos los aguinaldos como pagados?')
                ->modalDescription(function () {
                    $pending = $this->record->pending_aguinaldos_count;
                    return "Se marcarán {$pending} aguinaldo(s) pendiente(s) como pagados. ¿Confirma esta acción?";
                })
                ->modalSubmitActionLabel('Sí, marcar como pagados')
                ->action(function () {
                    $count = $this->record->aguinaldos()
                        ->where('status', 'pending')
                        ->get()
                        ->each(fn($a) => $a->markAsPaid())
                        ->count();

                    Notification::make()
                        ->success()
                        ->title('Aguinaldos marcados como pagados')
                        ->body("Se marcaron {$count} aguinaldo(s) como pagados para el período {$this->record->year}.")
                        ->send();

                    $this->refreshFormData(['status']);
                })
                ->visible(fn() => $this->record->isProcessing() && $this->record->pending_aguinaldos_count > 0),

            Action::make('export_excel')
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Exportar aguinaldos a Excel?')
                ->modalDescription("Se incluirán todos los aguinaldos generados para el período {$this->record->year} de {$this->record->company->name} en un archivo Excel descargable.")
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    Notification::make()
                        ->success()
                        ->title('Exportación lista')
                        ->body("La planilla de aguinaldos {$this->record->year} se está descargando.")
                        ->send();

                    return Excel::download(
                        new AguinaldosExport(periodId: $this->record->id),
                        'aguinaldos_año_' . $this->record->year . '_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                    );
                })
                ->visible(fn() => $this->record->isProcessing() || $this->record->isClosed()),

            Action::make('reopen_period')
                ->label('Reabrir')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('¿Reabrir Período de Aguinaldo?')
                ->modalDescription(
                    fn() => "Esta acción reabrirá el período de aguinaldo {$this->record->year} de {$this->record->company->name}, permitiendo generar nuevos aguinaldos o modificar los existentes. ¿Confirma que desea reabrir este período?"
                )
                ->modalSubmitActionLabel('Sí, reabrir período')
                ->action(function () {
                    $this->record->update([
                        'status'    => 'processing',
                        'closed_at' => null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Período reabierto')
                        ->body("El período de aguinaldo {$this->record->year} de {$this->record->company->name} ha sido reabierto.")
                        ->send();

                    $this->refreshFormData(['status', 'closed_at']);
                })
                ->visible(fn() => $this->record->isClosed()),

            EditAction::make()
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->visible(fn() => $this->record->isDraft()),

            Action::make('close_period')
                ->label('Cerrar')
                ->icon('heroicon-o-lock-closed')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('¿Cerrar Período de Aguinaldo?')
                ->modalDescription(function () {
                    $pending = $this->record->pending_aguinaldos_count;
                    $base = "Esta acción cerrará el período de aguinaldo {$this->record->year} de {$this->record->company->name}. Una vez cerrado no se podrán generar más aguinaldos.";
                    return $pending > 0
                        ? "{$base} Atención: aún hay {$pending} aguinaldo(s) pendiente(s) de pago que no podrán ser marcados como pagados después de cerrar el período."
                        : $base;
                })
                ->modalSubmitActionLabel('Sí, cerrar período')
                ->action(function () {
                    $this->record->update([
                        'status'    => 'closed',
                        'closed_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Período cerrado')
                        ->body("El período de aguinaldo {$this->record->year} de {$this->record->company->name} ha sido cerrado.")
                        ->send();

                    $this->refreshFormData(['status', 'closed_at']);
                })
                ->visible(fn() => $this->record->isProcessing()),

            Action::make('force_delete')
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Eliminar Período de Aguinaldo?')
                ->modalDescription(function () {
                    $count = $this->record->aguinaldos()->count();
                    return "Esta acción eliminará permanentemente el período {$this->record->year} de {$this->record->company->name} "
                        . "junto con {$count} aguinaldo(s) generado(s) y todos sus ítems. Esta acción no se puede deshacer.";
                })
                ->modalSubmitActionLabel('Sí, eliminar todo')
                ->action(function () {
                    $this->record->delete();

                    Notification::make()
                        ->success()
                        ->title('Período eliminado')
                        ->body("El período de aguinaldo {$this->record->year} de {$this->record->company->name} y todos sus aguinaldos fueron eliminados.")
                        ->send();

                    $this->redirect($this->getResource()::getUrl('index'));
                })
                ->visible(fn() => $this->record->isProcessing() || $this->record->isClosed()),
        ];
    }
}
