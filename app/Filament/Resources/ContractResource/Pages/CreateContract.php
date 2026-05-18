<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Resources\ContractResource;
use App\Models\Contract;
use App\Models\EmployeeBankAccount;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;

    /**
     * Antes de crear el contrato, valida que el empleado no tenga un contrato activo y que las fechas no se solapen. También asigna el usuario creador.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_id'] = Auth::id();

        // Validar que no tenga un contrato activo
        $hasActive = Contract::where('employee_id', $data['employee_id'])
            ->where('status', 'active')
            ->exists();

        if ($hasActive) {
            Notification::make()
                ->danger()
                ->title('Contrato activo existente')
                ->body('El empleado ya tiene un contrato vigente. Debe terminarlo o renovarlo antes de crear uno nuevo.')
                ->persistent()
                ->send();

            $this->halt();
        }

        // Art. 53 CLT: Validar duración máxima de 1 año para plazo fijo
        if (in_array($data['type'], ['plazo_fijo', 'aprendizaje']) && ! empty($data['end_date'])) {
            $start = \Carbon\Carbon::parse($data['start_date']);
            $end = \Carbon\Carbon::parse($data['end_date']);

            if ($end->gt($start->copy()->addYear())) {
                Notification::make()
                    ->danger()
                    ->title('Duración excedida')
                    ->body('Art. 53 del Código Laboral: Los contratos a plazo determinado no pueden exceder 1 año.')
                    ->persistent()
                    ->send();

                $this->halt();
            }
        }

        // Limpiar end_date para contratos indefinidos
        if ($data['type'] === 'indefinido') {
            $data['end_date'] = null;
        }

        return $data;
    }

    /**
     * Crea la cuenta bancaria del empleado si se ingresó una al crear el contrato con pago por débito.
     */
    protected function afterCreate(): void
    {
        if (
            ($this->data['payment_method'] ?? null) === 'debit' &&
            filled($this->data['ba_bank'] ?? null)
        ) {
            EmployeeBankAccount::firstOrCreate(
                ['employee_id' => $this->record->employee_id, 'status' => 'active'],
                [
                    'bank' => $this->data['ba_bank'],
                    'account_type' => $this->data['ba_account_type'],
                    'account_number' => $this->data['ba_account_number'],
                    'holder_name' => $this->data['ba_holder_name'],
                    'holder_ci' => $this->data['ba_holder_ci'],
                    'is_primary' => true,
                ]
            );
        }
    }

    /**
     * Personaliza el mensaje de notificación después de crear el contrato.
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Contrato creado exitosamente';
    }
}
