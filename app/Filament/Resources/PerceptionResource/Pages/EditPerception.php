<?php

namespace App\Filament\Resources\PerceptionResource\Pages;

use App\Filament\Resources\PerceptionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPerception extends EditRecord
{
    protected static string $resource = PerceptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Percepción actualizada exitosamente';
    }

    public function getRelationManagers(): array
    {
        return [
            PerceptionResource\RelationManagers\EmployeePerceptionsRelationManager::class,
        ];
    }
}
