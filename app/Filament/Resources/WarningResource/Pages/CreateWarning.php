<?php

namespace App\Filament\Resources\WarningResource\Pages;

use App\Filament\Resources\WarningResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

/** Página de creación de amonestaciones. */
class CreateWarning extends CreateRecord
{
    protected static string $resource = WarningResource::class;

    /**
     * Inyecta la fecha de hoy y el usuario logueado antes de crear el registro.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['issued_at'] = now()->toDateString();
        $data['issued_by_id'] = Auth::id();

        return $data;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Amonestación registrada')
            ->body('La amonestación fue registrada correctamente.');
    }
}
