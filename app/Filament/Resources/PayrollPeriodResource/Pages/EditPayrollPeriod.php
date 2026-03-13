<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Resources\PayrollPeriodResource;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPayrollPeriod extends EditRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),

            Action::make('generate_payrolls')
                ->label('Generar Recibos')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generar Recibos de Nómina')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de generar los recibos de nómina para el período {$this->record->name}? " .
                        "Esta acción creará recibos para los empleados activos que aún no tengan uno en este período."
                )
                ->action(function (PayrollService $payrollService) {
                    $count = $payrollService->generateForPeriod($this->record);

                    if ($count > 0) {
                        $this->record->update([
                            'status' => 'processing',
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Recibos generados')
                            ->body("Se generaron exitosamente {$count} recibos de nómina.")
                            ->send();

                        $this->refreshFormData([
                            'status',
                            'updated_at',
                        ]);
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('No se generaron recibos')
                            ->body('Todos los empleados activos ya tienen recibo en este período.')
                            ->send();
                    }
                })
                ->visible(fn() => in_array($this->record->status, ['draft', 'processing'])),

            Action::make('regenerate_payrolls')
                ->label('Regenerar Recibos')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerar Todos los Recibos')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de regenerar TODOS los recibos del período {$this->record->name}? " .
                        "Se recalcularán percepciones, deducciones, horas extras, ausencias y cuotas de préstamos. Solo se regenerarán los recibos en estado borrador."
                )
                ->action(function (PayrollService $payrollService) {
                    $payrolls = $this->record->payrolls()->where('status', 'draft')->with('employee')->get();

                    if ($payrolls->isEmpty()) {
                        Notification::make()
                            ->warning()
                            ->title('Sin recibos para regenerar')
                            ->body('No hay recibos en estado borrador para regenerar.')
                            ->send();
                        return;
                    }

                    $count = 0;
                    $failedEmployees = [];

                    foreach ($payrolls as $payroll) {
                        try {
                            $payrollService->regenerateForEmployee($payroll);
                            $count++;
                        } catch (\Throwable $e) {
                            $failedEmployees[] = $payroll->employee->full_name;
                        }
                    }

                    if (!empty($failedEmployees)) {
                        $names = implode(', ', $failedEmployees);
                        Notification::make()
                            ->warning()
                            ->title("Regeneración parcial")
                            ->body("Se regeneraron {$count} recibos. Fallaron: {$names}. Revise el log para más detalles.")
                            ->duration(10000)
                            ->send();
                    } else {
                        Notification::make()
                            ->success()
                            ->title('Recibos regenerados')
                            ->body("Se regeneraron exitosamente {$count} recibos de nómina.")
                            ->send();
                    }
                })
                ->visible(fn() => $this->record->status === 'processing' && $this->record->payrolls()->where('status', 'draft')->exists()),

            Action::make('revert_to_draft')
                ->label('Revertir a Borrador')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Revertir período a Borrador')
                ->modalDescription(function () {
                    $draftCount = $this->record->payrolls()->where('status', 'draft')->count();
                    return "Esta acción revertirá el período \"{$this->record->name}\" a estado Borrador " .
                        "y eliminará los {$draftCount} recibo(s) en borrador. " .
                        "Los recibos aprobados o pagados impiden esta acción.";
                })
                ->before(function (Action $action) {
                    $blockedCount = $this->record->payrolls()->whereIn('status', ['approved', 'paid'])->count();
                    if ($blockedCount > 0) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede revertir el período')
                            ->body("Hay {$blockedCount} recibo(s) aprobado(s) o pagado(s). Solo se puede revertir si todos los recibos están en borrador.")
                            ->duration(10000)
                            ->send();
                        $action->cancel();
                    }
                })
                ->action(function () {
                    $deleted = $this->record->payrolls()->where('status', 'draft')->delete();
                    $this->record->update(['status' => 'draft']);

                    Notification::make()
                        ->success()
                        ->title('Período revertido a Borrador')
                        ->body("Se eliminaron {$deleted} recibo(s) en borrador y el período volvió a estado Borrador.")
                        ->send();

                    $this->refreshFormData(['status', 'updated_at']);
                })
                ->visible(fn() => $this->record->status === 'processing'),

            Action::make('close_period')
                ->label('Cerrar Período')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cerrar Período de Nómina')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de cerrar el período {$this->record->name}? " .
                        "Una vez cerrado, no se podrán generar más recibos ni realizar modificaciones."
                )
                ->before(function (Action $action) {
                    // Regla 1: no se puede cerrar si quedan recibos sin pagar.
                    $unpaidCount = $this->record->payrolls()->whereNot('status', 'paid')->count();

                    if ($unpaidCount > 0) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede cerrar el período')
                            ->body("Hay {$unpaidCount} recibo(s) que aún no están en estado pagado. Pague todos los recibos antes de cerrar el período.")
                            ->duration(10000)
                            ->send();

                        $action->cancel();
                        return;
                    }

                    // Regla 2: no se puede cerrar si hay empleados activos sin recibo en este período.
                    // Usa los mismos filtros que generateForPeriod() para garantizar consistencia.
                    $payrollEmployeeIds = $this->record->payrolls()->pluck('employee_id');

                    $missingCount = Employee::where('status', 'active')
                        ->whereHas('activeContract', fn($q) => $q
                            ->where('payroll_type', $this->record->frequency)
                            ->whereNotNull('salary')
                        )
                        ->whereNotIn('id', $payrollEmployeeIds)
                        ->count();

                    if ($missingCount > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Hay empleados sin recibo')
                            ->body("Hay {$missingCount} empleado(s) activo(s) sin recibo en este período. Use 'Generar Recibos' antes de cerrar.")
                            ->duration(10000)
                            ->send();

                        $action->cancel();
                    }
                })
                ->action(function () {
                    $this->record->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Período cerrado')
                        ->body("El período {$this->record->name} ha sido cerrado exitosamente.")
                        ->send();

                    return redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                // Visible solo cuando todos los recibos del período están pagados.
                ->visible(fn() => $this->record->status === 'processing'
                    && $this->record->payrolls()->exists()
                    && $this->record->payrolls()->whereNot('status', 'paid')->doesntExist()),

            Action::make('reopen_period')
                ->label('Reabrir Período')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reabrir Período de Nómina')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de reabrir el período {$this->record->name}? " .
                        "Esto permitirá realizar modificaciones nuevamente."
                )
                ->action(function () {
                    $this->record->update([
                        'status' => 'processing',
                        'closed_at' => null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Período reabierto')
                        ->body("El período {$this->record->name} ha sido reabierto exitosamente.")
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'closed_at',
                        'updated_at',
                    ]);
                })
                ->visible(fn() => $this->record->status === 'closed'),

            DeleteAction::make()
                ->visible(fn() => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Período de Nómina')
                ->modalDescription(
                    fn() =>
                    "¿Está seguro de eliminar el período {$this->record->name}? " .
                        "Esta acción no se puede deshacer."
                )
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Período eliminado')
                        ->body('El período ha sido eliminado correctamente.')
                ),
        ];
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Período actualizado')
            ->body("El período \"{$this->record->name}\" ha sido actualizado correctamente.");
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Limpiar espacios en blanco
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        // Si no se proporciona un nombre, generar uno automáticamente
        if (empty($data['name'])) {
            $data['name'] = PayrollPeriod::generateName(
                $data['frequency'],
                Carbon::parse($data['start_date']),
                Carbon::parse($data['end_date']),
            );
        }

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Evitar que se modifiquen períodos cerrados
        if ($this->record->status === 'closed') {
            Notification::make()
                ->warning()
                ->title('Período cerrado')
                ->body('Este período está cerrado y no puede ser modificado.')
                ->persistent()
                ->send();
        }

        return $data;
    }
}
