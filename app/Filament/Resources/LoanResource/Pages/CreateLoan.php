<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use App\Settings\GeneralSettings;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    /**
     * Valida restricciones de negocio antes de crear el préstamo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $settings = app(GeneralSettings::class);
        $maxAmount = $settings->max_loan_amount;

        if ($data['amount'] > $maxAmount) {
            Notification::make()
                ->danger()
                ->title('Monto excede el límite')
                ->body('El monto máximo permitido es '.number_format($maxAmount, 0, ',', '.').' Gs.')
                ->send();

            $this->halt();
        }

        // Verificar que no tenga un préstamo pendiente o activo
        $existing = Loan::getActiveForEmployee($data['employee_id']);

        if ($existing) {
            $statusLabel = Loan::getStatusLabel($existing->status);

            Notification::make()
                ->danger()
                ->title('Ya existe un préstamo activo')
                ->body("El empleado ya tiene un préstamo en estado \"{$statusLabel}\". Debe completarse o cancelarse antes de crear uno nuevo.")
                ->persistent()
                ->send();

            $this->halt();
        }

        // Advertir sobre deuda total si excede el límite
        $currentDebt = Loan::getTotalActiveDebtForEmployee($data['employee_id']);
        $newTotal = $currentDebt + $data['amount'];

        if ($currentDebt > 0 && $newTotal > $maxAmount) {
            Notification::make()
                ->warning()
                ->title('Advertencia de deuda')
                ->body(
                    'El empleado ya tiene una deuda activa de '.number_format($currentDebt, 0, ',', '.').
                    ' Gs. Con este préstamo, la deuda total sería de '.number_format($newTotal, 0, ',', '.').' Gs.'
                )
                ->persistent()
                ->send();
        }

        return $data;
    }

    /**
     * Notificación de creación exitosa.
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Préstamo creado')
            ->body('El préstamo fue registrado en estado Pendiente.');
    }
}
