<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Pages\SalaryReport;
use App\Filament\Resources\DisbursementBatchResource;
use App\Filament\Resources\PayrollPeriodResource;
use App\Models\Company;
use App\Models\DisbursementBatch;
use App\Models\Employee;
use App\Models\Payroll;
use App\Services\PayrollService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewPayrollPeriod extends ViewRecord
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate_payrolls')
                ->label('Generar Recibos')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generar Recibos de Nómina')
                ->modalDescription(
                    fn () => "¿Está seguro de generar los recibos de nómina para la planilla {$this->record->name}? ".
                        'Esta acción creará recibos para los empleados activos que aún no tengan uno en este período.'
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
                            ->body('Todos los empleados activos ya tienen recibo en esta planilla.')
                            ->send();
                    }
                })
                ->visible(fn () => in_array($this->record->status, ['draft', 'processing'])),

            Action::make('generate_for_employee')
                ->label('Generar para Empleado')
                ->icon('heroicon-o-user-plus')
                ->color('primary')
                ->modalHeading('Generar Recibo para Empleado')
                ->modalSubmitActionLabel('Generar')
                ->form([
                    Select::make('employee_id')
                        ->label('Empleado')
                        ->options(function () {
                            $existingIds = $this->record->payrolls()->pluck('employee_id');

                            return Employee::where('status', 'active')
                                ->whereHas('activeContract', fn ($q) => $q
                                    ->where('payroll_type', $this->record->frequency)
                                    ->whereNotNull('salary')
                                )
                                ->whereNotIn('id', $existingIds)
                                ->get()
                                ->mapWithKeys(fn ($e) => [$e->id => "{$e->full_name} — CI: {$e->ci}"])
                                ->toArray();
                        })
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->placeholder('Seleccione un empleado sin recibo en esta planilla'),
                ])
                ->action(function (array $data, PayrollService $payrollService) {
                    try {
                        $employee = Employee::findOrFail($data['employee_id']);
                        $payrollService->generateForEmployee($employee, $this->record);

                        if ($this->record->status === 'draft') {
                            $this->record->update(['status' => 'processing']);
                        }

                        Notification::make()
                            ->success()
                            ->title('Recibo generado')
                            ->body("El recibo de {$employee->full_name} fue generado exitosamente.")
                            ->send();

                        $this->refreshFormData(['status', 'updated_at']);
                    } catch (\InvalidArgumentException $e) {
                        Notification::make()
                            ->danger()
                            ->title('No se pudo generar el recibo')
                            ->body($e->getMessage())
                            ->persistent()
                            ->send();
                    }
                })
                ->visible(fn () => in_array($this->record->status, ['draft', 'processing'])),

            Action::make('regenerate_payrolls')
                ->label('Regenerar Recibos')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerar Todos los Recibos')
                ->modalDescription(
                    fn () => "¿Está seguro de regenerar TODOS los recibos de la planilla {$this->record->name}? ".
                        'Se recalcularán percepciones, deducciones, horas extras, ausencias y cuotas de préstamos. Solo se regenerarán los recibos en estado borrador.'
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
                        } catch (\Throwable) {
                            $failedEmployees[] = $payroll->employee->full_name;
                        }
                    }

                    if (! empty($failedEmployees)) {
                        $names = implode(', ', $failedEmployees);
                        Notification::make()
                            ->warning()
                            ->title('Regeneración parcial')
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
                ->visible(fn () => $this->record->status === 'processing' && $this->record->payrolls()->where('status', 'draft')->exists()),

            Action::make('revert_to_draft')
                ->label('Revertir a Borrador')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Revertir planilla a Borrador')
                ->modalDescription(function () {
                    $draftCount = $this->record->payrolls()->where('status', 'draft')->count();

                    return "Esta acción revertirá la planilla \"{$this->record->name}\" a estado Borrador ".
                        "y eliminará los {$draftCount} recibo(s) en borrador. ".
                        'Los recibos aprobados o pagados impiden esta acción.';
                })
                ->before(function (Action $action) {
                    $blockedCount = $this->record->payrolls()->whereIn('status', ['approved', 'paid'])->count();
                    if ($blockedCount > 0) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede revertir la planilla')
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
                        ->title('Planilla revertida a Borrador')
                        ->body("Se eliminaron {$deleted} recibo(s) en borrador y la planilla volvió a estado Borrador.")
                        ->send();

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn () => $this->record->status === 'processing'),

            Action::make('mark_cash_paid')
                ->label('Marcar Efectivo como Pagado')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Marcar recibos en efectivo como pagados')
                ->modalDescription(function () {
                    $count = $this->record->payrolls()
                        ->where('payment_method', 'cash')
                        ->whereIn('status', ['approved'])
                        ->count();

                    return "Se marcarán como Pagados {$count} recibo(s) aprobados con método de pago Efectivo.";
                })
                ->modalSubmitActionLabel('Sí, marcar como pagados')
                ->before(function (Action $action) {
                    $count = $this->record->payrolls()
                        ->where('payment_method', 'cash')
                        ->where('status', 'approved')
                        ->count();

                    if ($count === 0) {
                        Notification::make()
                            ->warning()
                            ->title('Sin recibos para marcar')
                            ->body('No hay recibos aprobados con método de pago Efectivo en esta planilla.')
                            ->send();

                        $action->cancel();
                    }
                })
                ->action(function () {
                    $updated = $this->record->payrolls()
                        ->where('payment_method', 'cash')
                        ->where('status', 'approved')
                        ->update(['status' => 'paid']);

                    Notification::make()
                        ->success()
                        ->title('Recibos marcados como pagados')
                        ->body("{$updated} recibo(s) en efectivo fueron marcados como Pagados.")
                        ->send();

                    $this->refreshFormData(['status', 'updated_at']);
                })
                ->visible(fn () => in_array($this->record->status, ['processing', 'closed'])
                    && $this->record->payrolls()->where('payment_method', 'cash')->where('status', 'approved')->exists()),

            Action::make('close_period')
                ->label('Cerrar Planilla')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cerrar Planilla de Nómina')
                ->modalDescription(function () {
                    $paid = $this->record->payrolls()->where('status', 'paid')->count();
                    $disbursed = $this->record->payrolls()->where('status', 'disbursed')->count();
                    $total = $paid + $disbursed;

                    $detail = $disbursed > 0
                        ? "{$paid} pagados y {$disbursed} acreditados por el banco"
                        : "{$paid} pagados";

                    return "La planilla {$this->record->name} tiene {$total} recibos listos ({$detail}). ".
                        'Una vez cerrada, no se podrán generar más recibos ni realizar modificaciones.';
                })
                ->before(function (Action $action) {
                    // Regla 1: no se puede cerrar si quedan recibos en draft/approved.
                    $pendingCount = $this->record->payrolls()
                        ->whereIn('status', ['draft', 'approved'])
                        ->count();

                    if ($pendingCount > 0) {
                        Notification::make()
                            ->danger()
                            ->title('No se puede cerrar la planilla')
                            ->body("Hay {$pendingCount} recibo(s) en borrador o aprobado sin acreditar. Procesá todos los recibos antes de cerrar.")
                            ->duration(10000)
                            ->send();

                        $action->cancel();

                        return;
                    }

                    // Regla 2: no se puede cerrar si hay empleados activos sin recibo en esta planilla.
                    $payrollEmployeeIds = $this->record->payrolls()->pluck('employee_id');

                    $missingCount = Employee::where('status', 'active')
                        ->whereHas('activeContract', fn ($q) => $q
                            ->where('payroll_type', $this->record->frequency)
                            ->whereNotNull('salary')
                        )
                        ->whereNotIn('id', $payrollEmployeeIds)
                        ->count();

                    if ($missingCount > 0) {
                        Notification::make()
                            ->warning()
                            ->title('Hay empleados sin recibo')
                            ->body("Hay {$missingCount} empleado(s) activo(s) sin recibo en esta planilla. Use 'Generar Recibos' antes de cerrar.")
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
                        ->title('Planilla cerrada')
                        ->body("La planilla {$this->record->name} ha sido cerrada exitosamente.")
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'closed_at',
                        'updated_at',
                    ]);
                })
                // Visible cuando todos los recibos están en disbursed o paid (ninguno en draft/approved).
                ->visible(fn () => $this->record->status === 'processing'
                    && $this->record->payrolls()->exists()
                    && $this->record->payrolls()->whereIn('status', ['draft', 'approved'])->doesntExist()),

            Action::make('reopen_period')
                ->label('Reabrir Planilla')
                ->icon('heroicon-o-lock-open')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reabrir Planilla de Nómina')
                ->modalDescription(
                    fn () => "¿Está seguro de reabrir la planilla {$this->record->name}? ".
                        'Esto permitirá realizar modificaciones nuevamente.'
                )
                ->action(function () {
                    $this->record->update([
                        'status' => 'processing',
                        'closed_at' => null,
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Planilla reabierta')
                        ->body("La planilla {$this->record->name} ha sido reabierta exitosamente.")
                        ->send();

                    $this->refreshFormData([
                        'status',
                        'closed_at',
                        'updated_at',
                    ]);
                })
                ->visible(fn () => $this->record->status === 'closed'),

            Action::make('salary_report')
                ->label('Reporte de Salarios')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->url(fn () => SalaryReport::getUrl().'?tableFilters[period_id][value]='.$this->record->id)
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->payrolls()->exists()),

            Action::make('create_payroll_batch')
                ->label('Enviar al Banco')
                ->icon('heroicon-o-building-library')
                ->color('info')
                ->mountUsing(function (\Filament\Forms\Form $form, Action $action) {
                    $hasPayrolls = Payroll::query()
                        ->where('payroll_period_id', $this->record->id)
                        ->where('status', 'approved')
                        ->where('payment_method', 'transfer')
                        ->whereNull('disbursement_batch_id')
                        ->exists();

                    if (! $hasPayrolls) {
                        Notification::make()
                            ->warning()
                            ->title('Sin recibos disponibles')
                            ->body('No hay recibos aprobados por transferencia sin lote asignado en esta planilla.')
                            ->send();

                        $action->halt();

                        return;
                    }

                    $form->fill(['fecha_credito' => today()->format('Y-m-d')]);
                })
                ->modalHeading('Crear lote de pago bancario')
                ->modalSubmitActionLabel('Crear lote')
                ->form(function () {
                    $companyOptions = Company::query()
                        ->whereHas('branches.employees.payrolls', fn ($q) => $q
                            ->where('payroll_period_id', $this->record->id)
                            ->where('status', 'approved')
                            ->where('payment_method', 'transfer')
                            ->whereNull('disbursement_batch_id')
                        )
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray();

                    return [
                        Select::make('company_id')
                            ->label('Empresa')
                            ->options($companyOptions)
                            ->required()
                            ->native(false)
                            ->visible(fn () => count($companyOptions) > 1)
                            ->default(count($companyOptions) === 1 ? array_key_first($companyOptions) : null)
                            ->helperText('Solo se muestran empresas con recibos aprobados por transferencia sin lote.'),

                        DatePicker::make('fecha_credito')
                            ->label('Fecha de acreditación')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection()
                            ->helperText('Fecha en que el banco acreditará los fondos en las cuentas de los empleados.'),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->placeholder('Observaciones opcionales...')
                            ->rows(2),
                    ];
                })
                ->action(function (array $data) {
                    $companyOptions = Company::query()
                        ->whereHas('branches.employees.payrolls', fn ($q) => $q
                            ->where('payroll_period_id', $this->record->id)
                            ->where('status', 'approved')
                            ->where('payment_method', 'transfer')
                            ->whereNull('disbursement_batch_id')
                        )
                        ->pluck('id')
                        ->toArray();

                    $companyId = $data['company_id'] ?? (count($companyOptions) === 1 ? $companyOptions[0] : null);

                    if (! $companyId) {
                        Notification::make()->danger()->title('Seleccione una empresa')->send();

                        return;
                    }

                    $batch = DisbursementBatch::create([
                        'type' => 'payroll',
                        'company_id' => $companyId,
                        'fecha_credito' => $data['fecha_credito'],
                        'status' => 'pending',
                        'notes' => $data['notes'] ?? null,
                        'created_by_id' => Auth::id(),
                    ]);

                    $updated = Payroll::query()
                        ->where('payroll_period_id', $this->record->id)
                        ->where('status', 'approved')
                        ->where('payment_method', 'transfer')
                        ->whereNull('disbursement_batch_id')
                        ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $companyId))
                        ->update(['disbursement_batch_id' => $batch->id]);

                    Notification::make()
                        ->success()
                        ->title('Lote creado')
                        ->body("Se creó el lote con {$updated} ".($updated === 1 ? 'recibo' : 'recibos').'. Redirigiendo...')
                        ->send();

                    $this->redirect(DisbursementBatchResource::getUrl('view', ['record' => $batch]));
                })
                ->visible(fn () => in_array($this->record->status, ['processing', 'closed'])),

            EditAction::make()
                ->visible(fn () => $this->record->status !== 'closed'),

            DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Planilla de Nómina')
                ->modalDescription(
                    fn () => "¿Está seguro de eliminar la planilla {$this->record->name}? ".
                        'Esta acción no se puede deshacer.'
                )
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Planilla eliminada')
                        ->body('La planilla ha sido eliminada correctamente.')
                ),
        ];
    }
}
