<?php

namespace App\Filament\Resources\VacationResource\Pages;

use App\Filament\Resources\VacationResource;
use App\Models\Employee;
use App\Models\Vacation;
use App\Services\VacationService;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateVacation extends CreateRecord
{
    protected static string $resource = VacationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Asignar el balance correspondiente
        if (!empty($data['employee_id']) && !empty($data['start_date'])) {
            $employee = Employee::find($data['employee_id']);
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
