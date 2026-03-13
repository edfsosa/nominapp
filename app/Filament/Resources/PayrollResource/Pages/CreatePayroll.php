<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Resources\PayrollResource;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreatePayroll extends CreateRecord
{
    protected static string $resource = PayrollResource::class;

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Generar Recibo')
                ->description('Seleccione el empleado y el período. El recibo se calculará automáticamente con todos los ítems correspondientes.')
                ->schema([
                    Select::make('employee_id')
                        ->label('Empleado')
                        ->relationship('employee', 'id')
                        ->searchable()
                        ->preload()
                        ->native(false)
                        ->required()
                        ->getOptionLabelFromRecordUsing(fn($record) => "{$record->first_name} {$record->last_name} - CI: {$record->ci}"),

                    Select::make('payroll_period_id')
                        ->label('Período')
                        ->options(
                            PayrollPeriod::whereIn('status', ['draft', 'processing'])
                                ->orderByDesc('start_date')
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->helperText('Solo se muestran períodos en borrador o en proceso.'),
                ])
                ->columns(2),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $employee = Employee::findOrFail($data['employee_id']);
            $period   = PayrollPeriod::findOrFail($data['payroll_period_id']);

            return app(PayrollService::class)->generateForEmployee($employee, $period);
        } catch (\InvalidArgumentException $e) {
            Notification::make()
                ->danger()
                ->title('No se pudo generar el recibo')
                ->body($e->getMessage())
                ->persistent()
                ->send();

            throw new Halt();
        }
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Recibo generado')
            ->body("El recibo para {$this->record->employee->full_name} ha sido generado exitosamente.");
    }
}
