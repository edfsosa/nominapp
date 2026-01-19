<?php

namespace App\Filament\Resources\LoanResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\LoanResource;
use App\Settings\GeneralSettings;
use Filament\Notifications\Notification;
use App\Models\Loan;

class CreateLoan extends CreateRecord
{
    protected static string $resource = LoanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Calcular el monto de la cuota si no está calculado
        if (empty($data['installment_amount']) && !empty($data['amount']) && !empty($data['installments_count'])) {
            $data['installment_amount'] = round($data['amount'] / $data['installments_count'], 0);
        }

        // Validar que no exceda el límite
        $settings = app(GeneralSettings::class);
        $maxAmount = $settings->max_loan_amount;

        if ($data['amount'] > $maxAmount) {
            Notification::make()
                ->danger()
                ->title('Monto excede el límite')
                ->body("El monto máximo permitido es " . number_format($maxAmount, 0, ',', '.') . " Gs.")
                ->send();

            $this->halt();
        }

        // Validar que no tenga un préstamo/adelanto del mismo tipo pendiente o activo
        $existingLoan = Loan::where('employee_id', $data['employee_id'])
            ->where('type', $data['type'])
            ->whereIn('status', ['pending', 'active'])
            ->first();

        if ($existingLoan) {
            $typeLabel = Loan::getTypeLabel($data['type']);
            $statusLabel = Loan::getStatusLabel($existingLoan->status);

            Notification::make()
                ->danger()
                ->title('Ya existe un ' . strtolower($typeLabel))
                ->body("El empleado ya tiene un {$typeLabel} en estado \"{$statusLabel}\". Debe completarse o cancelarse antes de crear uno nuevo.")
                ->persistent()
                ->send();

            $this->halt();
        }

        // Validar deuda total del empleado para préstamos (no adelantos)
        if ($data['type'] === 'loan') {
            $currentDebt = Loan::getTotalActiveDebtForEmployee($data['employee_id']);
            $newTotal = $currentDebt + $data['amount'];

            if ($newTotal > $maxAmount) {
                Notification::make()
                    ->warning()
                    ->title('Advertencia de deuda')
                    ->body("El empleado ya tiene una deuda activa de " . number_format($currentDebt, 0, ',', '.') . " Gs. Con este préstamo, la deuda total sería de " . number_format($newTotal, 0, ',', '.') . " Gs.")
                    ->persistent()
                    ->send();
            }
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Préstamo creado exitosamente';
    }
}
