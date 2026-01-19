<?php

namespace App\Filament\Resources\AbsentResource\Pages;

use Filament\Actions;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\AbsentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAbsent extends CreateRecord
{
    protected static string $resource = AbsentResource::class;

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
