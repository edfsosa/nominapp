<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Models\Loan;
use App\Models\Payroll;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\LoanInstallment;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Support\Enums\MaxWidth;
use App\Filament\Resources\LoanResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OverdueInstallmentsExport;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    /**
     * Cache de conteos para badges de tabs
     */
    protected ?array $loanCounts = null;

    /**
     * Obtiene los conteos cacheados para los badges
     */
    protected function getLoanCounts(): array
    {
        if ($this->loanCounts === null) {
            $this->loanCounts = Loan::getCounts();
        }

        return $this->loanCounts;
    }

    /**
     * Define las acciones del encabezado de la página
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateMonthlyAdvances')
                ->label('Generar Adelantos')
                ->icon('heroicon-o-bolt')
                ->color('warning')
                ->form([
                    TextInput::make('percentage')
                        ->label('Porcentaje del Salario')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(50)
                        ->suffix('%')
                        ->helperText('El monto se calculará como este porcentaje del salario base de cada empleado (máximo 50%)'),

                    Select::make('employee_ids')
                        ->label('Empleados')
                        ->multiple()
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            // Obtener IDs de empleados que ya tienen adelanto activo o pendiente
                            $employeesWithActiveAdvance = Loan::where('type', 'advance')
                                ->whereIn('status', ['pending', 'active'])
                                ->pluck('employee_id')
                                ->toArray();

                            // Obtener IDs de empleados que ya tienen nómina del período actual
                            $now = Carbon::now();
                            $employeesWithCurrentPayroll = collect();

                            // Buscar períodos actuales por cada tipo de nómina
                            $currentPeriods = PayrollPeriod::where('start_date', '<=', $now)
                                ->where('end_date', '>=', $now)
                                ->get();

                            foreach ($currentPeriods as $period) {
                                $employeeIds = Payroll::where('payroll_period_id', $period->id)
                                    ->pluck('employee_id');
                                $employeesWithCurrentPayroll = $employeesWithCurrentPayroll->merge($employeeIds);
                            }

                            $excludeIds = array_merge(
                                $employeesWithActiveAdvance,
                                $employeesWithCurrentPayroll->unique()->toArray()
                            );

                            // Empleados activos con salario base, sin adelanto activo/pendiente y sin nómina actual
                            return Employee::where('status', 'active')
                                ->whereNotNull('base_salary')
                                ->where('base_salary', '>', 0)
                                ->whereNotIn('id', $excludeIds)
                                ->get()
                                ->mapWithKeys(fn($employee) => [
                                    $employee->id => "{$employee->full_name} - CI: {$employee->ci} - Salario: " . number_format($employee->base_salary, 0, ',', '.') . " Gs."
                                ]);
                        })
                        ->helperText('Solo empleados activos con salario base, sin adelanto pendiente/activo y sin nómina del período actual'),

                    Textarea::make('reason')
                        ->label('Motivo')
                        ->placeholder('Adelanto de salario')
                        ->maxLength(255)
                        ->nullable()
                        ->default('Adelanto de salario')
                        ->rows(1),
                ])
                ->modalHeading('Generar Adelantos Masivos')
                ->modalDescription('Se crearán adelantos en estado pendiente. El monto se calculará según el porcentaje del salario de cada empleado.')
                ->modalSubmitActionLabel('Generar Adelantos')
                ->action(function (array $data): void {
                    $count = 0;
                    $percentage = $data['percentage'] / 100;

                    foreach ($data['employee_ids'] as $employeeId) {
                        $employee = Employee::find($employeeId);

                        if (!$employee || !$employee->base_salary) {
                            continue;
                        }

                        $amount = round($employee->base_salary * $percentage, 0);

                        Loan::create([
                            'employee_id' => $employeeId,
                            'type' => 'advance',
                            'amount' => $amount,
                            'installments_count' => 1,
                            'installment_amount' => $amount,
                            'status' => 'pending',
                            'reason' => $data['reason'] ?? 'Adelanto de salario',
                        ]);
                        $count++;
                    }

                    Notification::make()
                        ->title('Adelantos generados')
                        ->body("Se crearon {$count} adelantos en estado pendiente ({$data['percentage']}% del salario).")
                        ->success()
                        ->send();
                }),

            Action::make('overdueInstallments')
                ->label('Cuotas Vencidas')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->badge(fn() => LoanInstallment::overdue()->count() ?: null)
                ->badgeColor('danger')
                ->modalHeading('Cuotas Vencidas')
                ->modalDescription(fn() => 'Hay ' . LoanInstallment::overdue()->count() . ' cuotas vencidas pendientes de pago.')
                ->modalContent(function () {
                    $overdueInstallments = LoanInstallment::overdue()
                        ->with(['loan.employee'])
                        ->orderBy('due_date')
                        ->get();

                    return view('filament.resources.loan-resource.pages.overdue-installments-modal', [
                        'installments' => $overdueInstallments,
                    ]);
                })
                ->modalWidth(MaxWidth::FourExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->extraModalFooterActions([
                    Action::make('exportOverdue')
                        ->label('Exportar a Excel')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function () {
                            return Excel::download(
                                new OverdueInstallmentsExport(),
                                'cuotas_vencidas_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                            );
                        }),
                ]),

            CreateAction::make()
                ->label('Nuevo Préstamo/Adelanto')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Define las pestañas de filtrado
     *
     * @return array
     */
    public function getTabs(): array
    {
        $counts = $this->getLoanCounts();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['total']),

            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending'))
                ->badge($counts['by_status']['pending'])
                ->badgeColor('warning'),

            'active' => Tab::make('Activos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'active'))
                ->badge($counts['by_status']['active'])
                ->badgeColor('info'),

            'paid' => Tab::make('Pagados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'paid'))
                ->badge($counts['by_status']['paid'])
                ->badgeColor('success'),

            'loans' => Tab::make('Préstamos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'loan'))
                ->badge($counts['by_type']['loan'])
                ->badgeColor('info')
                ->icon('heroicon-o-banknotes'),

            'advances' => Tab::make('Adelantos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'advance'))
                ->badge($counts['by_type']['advance'])
                ->badgeColor('warning')
                ->icon('heroicon-o-clock'),
        ];
    }

    /**
     * Define la pestaña activa por defecto
     *
     * @return string|int|null
     */
    public function getDefaultActiveTab(): string | int | null
    {
        return 'pending';
    }
}
