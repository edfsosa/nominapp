<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Resources\PayrollPeriodResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollPeriod extends CreateRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Período creado')
            ->body("El período \"{$this->record->name}\" ha sido creado exitosamente.");
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Limpiar espacios en blanco
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        // Si no se proporciona un nombre, generar uno automáticamente
        if (empty($data['name'])) {
            $startDate = \Carbon\Carbon::parse($data['start_date']);
            $endDate = \Carbon\Carbon::parse($data['end_date']);

            $data['name'] = match ($data['frequency']) {
                'monthly' => $startDate->locale('es')->isoFormat('MMMM YYYY'),
                'biweekly' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
                'weekly' => 'Semana del ' . $startDate->format('d/m/Y'),
                default => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            };
        }

        return $data;
    }
}
