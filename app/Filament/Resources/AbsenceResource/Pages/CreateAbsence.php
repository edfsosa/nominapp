<?php

namespace App\Filament\Resources\AbsenceResource\Pages;

use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\AbsenceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAbsence extends CreateRecord
{
    protected static string $resource = AbsenceResource::class;

    /**
     * Modifica los datos del formulario antes de crear el registro.
     *
     * @param array $data
     * @return array
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['reported_by_id'] = Auth::id();
        return $data;
    }
}
