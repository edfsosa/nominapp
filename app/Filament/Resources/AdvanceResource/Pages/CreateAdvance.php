<?php

namespace App\Filament\Resources\AdvanceResource\Pages;

use App\Filament\Resources\AdvanceResource;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Settings\PayrollSettings;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateAdvance extends CreateRecord
{
    protected static string $resource = AdvanceResource::class;

    /**
     * Valida restricciones de negocio antes de crear el adelanto.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $employee = Employee::find($data['employee_id']);

        if (! $employee) {
            Notification::make()
                ->danger()
                ->title('Error')
                ->body('No se encontró el empleado seleccionado.')
                ->send();
            $this->halt();
        }

        $referenceSalary = $employee->getAdvanceReferenceSalary();

        if (! $referenceSalary) {
            Notification::make()
                ->danger()
                ->title('Empleado sin salario definido')
                ->body('Los adelantos requieren que el empleado tenga salario mensual o jornal definido en su contrato activo.')
                ->send();
            $this->halt();
        }

        $maxAdvance = $employee->getMaxAdvanceAmount();

        if ($data['amount'] > $maxAdvance) {
            Notification::make()
                ->danger()
                ->title('Monto de adelanto excedido')
                ->body(
                    'El máximo para este empleado es '.
                    number_format($maxAdvance, 0, ',', '.').
                    ' Gs. ('.((int) app(PayrollSettings::class)->advance_max_percent).'% de '.number_format($referenceSalary, 0, ',', '.').' Gs.)'
                )
                ->persistent()
                ->send();
            $this->halt();
        }

        // Verificar límite de adelantos por período de nómina
        $settings = app(PayrollSettings::class);
        $maxPerPeriod = $settings->advance_max_per_period;

        if ($maxPerPeriod > 0) {
            $payrollType = $employee->activeContract?->payroll_type ?? 'monthly';

            $period = PayrollPeriod::where('frequency', $payrollType)
                ->where('start_date', '<=', now())
                ->where('end_date', '>=', now())
                ->first();

            if ($period) {
                $countInPeriod = Advance::where('employee_id', $data['employee_id'])
                    ->whereNotIn('status', ['cancelled', 'rejected'])
                    ->whereBetween('created_at', [$period->start_date, $period->end_date->endOfDay()])
                    ->count();

                if ($countInPeriod >= $maxPerPeriod) {
                    Notification::make()
                        ->danger()
                        ->title('Límite de adelantos alcanzado')
                        ->body("Este empleado ya tiene {$countInPeriod} adelanto(s) en el período actual (máximo configurado: {$maxPerPeriod}).")
                        ->persistent()
                        ->send();

                    $this->halt();
                }
            }
        }

        return $data;
    }

    /**
     * Notificación de creación exitosa.
     */
    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Adelanto creado')
            ->body('El adelanto fue registrado en estado Pendiente.');
    }
}
