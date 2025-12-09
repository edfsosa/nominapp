<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Resources\PayrollPeriodResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPayrollPeriod extends EditRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),

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

                    return redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn() => $this->record->status !== 'closed'),

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

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Período actualizado')
            ->body("El período \"{$this->record->name}\" ha sido actualizado correctamente.");
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Limpiar espacios en blanco
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        // Si no se proporciona un nombre, generar uno automáticamente
        if (empty($data['name'])) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            $data['name'] = match ($data['frequency']) {
                'monthly' => $startDate->locale('es')->isoFormat('MMMM YYYY'),
                'biweekly' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
                'weekly' => 'Semana del ' . $startDate->format('d/m/Y'),
                default => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            };
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Evitar que se modifiquen períodos cerrados
        if ($this->record->status === 'closed') {
            Notification::make()
                ->warning()
                ->title('Período cerrado')
                ->body('Este período está cerrado y no puede ser modificado.')
                ->persistent()
                ->send();
        }

        return $data;
    }
}
