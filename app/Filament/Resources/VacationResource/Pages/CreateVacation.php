<?php

namespace App\Filament\Resources\VacationResource\Pages;

use App\Filament\Resources\VacationResource;
use App\Models\Employee;
use App\Services\VacationService;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateVacation extends CreateRecord
{
    protected static string $resource = VacationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function beforeCreate(): void
    {
        $data = $this->data;

        if (empty($data['employee_id']) || empty($data['start_date']) || empty($data['end_date'])) {
            Notification::make()
                ->danger()
                ->title('Formulario incompleto')
                ->body('Seleccione un empleado con derecho a vacaciones y complete las fechas del período.')
                ->send();

            $this->halt();

            return;
        }

        $employee = Employee::find($data['employee_id']);
        if (! $employee instanceof Employee) {
            return;
        }

        $validation = VacationService::validateRequest(
            $employee,
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
        );

        if (! $validation['valid']) {
            Notification::make()
                ->danger()
                ->title('No se puede crear la solicitud')
                ->body(implode(' | ', $validation['errors']))
                ->send();

            $this->halt();
        }

        foreach ($validation['warnings'] ?? [] as $warning) {
            Notification::make()
                ->warning()
                ->title('Advertencia')
                ->body($warning)
                ->send();
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'pending';

        if (! empty($data['employee_id']) && ! empty($data['start_date'])) {
            $employee = Employee::find($data['employee_id']);
            if (! $employee instanceof Employee) {
                return $data;
            }
            $year = Carbon::parse($data['start_date'])->year;
            $balance = VacationService::getOrCreateBalance($employee, $year);
            $data['vacation_balance_id'] = $balance->id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Agregar días pendientes al balance
        $record = $this->record;

        if ($record->vacation_balance_id && $record->vacationBalance) {
            $record->vacationBalance->addPendingDays($record->business_days ?? 0);
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Solicitud de vacaciones creada exitosamente';
    }
}
