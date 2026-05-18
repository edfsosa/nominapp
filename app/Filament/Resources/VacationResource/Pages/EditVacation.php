<?php

namespace App\Filament\Resources\VacationResource\Pages;

use App\Filament\Resources\VacationResource;
use App\Models\Employee;
use App\Services\VacationService;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVacation extends EditRecord
{
    protected static string $resource = VacationResource::class;

    /**
     * Redirige a la vista si la vacación ya fue aprobada.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->status === 'approved') {
            Notification::make()
                ->warning()
                ->title('No editable')
                ->body('Las vacaciones aprobadas no pueden modificarse.')
                ->send();

            $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
        }
    }

    /**
     * Define las acciones del encabezado de la página.
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->icon('heroicon-o-eye')
                ->color('primary'),

            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->before(function () {
                    VacationService::releaseOnDelete($this->record);
                }),
        ];
    }

    /**
     * Define la URL a la que se redirige después de guardar.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Mutar los datos del formulario antes de guardarlos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
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

    /**
     * Ajusta el balance de días pendientes si cambiaron los días hábiles.
     */
    protected function beforeSave(): void
    {
        $record = $this->record;
        $oldBusinessDays = $record->getOriginal('business_days') ?? 0;
        $newBusinessDays = $this->data['business_days'] ?? 0;

        if ($record->isPending() && $oldBusinessDays !== $newBusinessDays) {
            if ($record->vacation_balance_id && $record->vacationBalance) {
                $record->vacationBalance->releasePendingDays($oldBusinessDays);
                $record->vacationBalance->addPendingDays($newBusinessDays);
            }
        }
    }

    /**
     * Define el título de la notificación después de guardar.
     */
    protected function getSavedNotificationTitle(): ?string
    {
        return 'Solicitud de vacaciones actualizada';
    }
}
