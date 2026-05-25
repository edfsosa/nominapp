<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Resources\PayrollPeriodResource;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollPeriod extends CreateRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    /**
     * Personaliza la notificación que se muestra después de crear un período de nómina exitosamente.
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Planilla creada')
            ->body("La planilla \"{$this->record->name}\" ha sido creada exitosamente.");
    }

    /**
     * Permite modificar los datos del formulario antes de crear el registro en la base de datos.
     * En este caso, se limpia el nombre y se genera automáticamente si no se proporciona uno.
     *
     * @param  array  $data  Los datos del formulario antes de la creación.
     * @return array Los datos del formulario después de la mutación.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['name'] = PayrollPeriod::generateName(
            $data['frequency'],
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
        );

        return $data;
    }
}
