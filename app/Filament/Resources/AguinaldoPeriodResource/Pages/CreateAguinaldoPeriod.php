<?php

namespace App\Filament\Resources\AguinaldoPeriodResource\Pages;

use App\Filament\Resources\AguinaldoPeriodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAguinaldoPeriod extends CreateRecord
{
    protected static string $resource = AguinaldoPeriodResource::class;

    /**
     * Muta los datos del formulario antes de crear el registro.
     *
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'draft';

        return $data;
    }

    /**
     * Obtiene el título de la notificación que se mostrará después de crear el registro.
     *
     * @return string|null
     */
    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Período de aguinaldo creado exitosamente';
    }
}
