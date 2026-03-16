<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Models\Loan;
use App\Models\Payroll;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\LoansExport;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use App\Filament\Resources\LoanResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    /**
     * Cache de conteos para badges de tabs
     */
    protected ?array $loanCounts = null;

    /**
     * Obtiene los conteos de préstamos para badges de tabs, con caching para evitar consultas repetidas
     *
     * @return array
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
                            $now = Carbon::now();

                            // IDs con adelanto activo o pendiente
                            $withActiveAdvance = Loan::where('type', 'advance')
                                ->whereIn('status', ['pending', 'active'])
                                ->pluck('employee_id');

                            // IDs con nómina en algún período activo actualmente
                            $currentPeriodIds = PayrollPeriod::where('start_date', '<=', $now)
                                ->where('end_date', '>=', $now)
                                ->pluck('id');

                            $withCurrentPayroll = Payroll::whereIn('payroll_period_id', $currentPeriodIds)
                                ->pluck('employee_id');

                            $excludeIds = $withActiveAdvance->merge($withCurrentPayroll)->unique();

                            return Employee::where('status', 'active')
                                ->whereHas('activeContract', fn($q) => $q->where('salary_type', 'mensual')->where('salary', '>', 0))
                                ->whereNotIn('id', $excludeIds)
                                ->with('activeContract')
                                ->get()
                                ->mapWithKeys(fn($employee) => [
                                    $employee->id => "{$employee->full_name} - CI: {$employee->ci} - Salario: " . number_format($employee->base_salary, 0, ',', '.') . " Gs."
                                ]);
                        })
                        ->helperText('Solo empleados activos con salario mensual, sin adelanto pendiente/activo y sin nómina del período actual'),
                ])
                ->modalHeading('Generar Adelantos Masivos')
                ->modalDescription('Se crearán adelantos en estado pendiente. El monto se calculará según el porcentaje del salario de cada empleado.')
                ->modalSubmitActionLabel('Generar Adelantos')
                ->action(function (array $data): void {
                    $count = 0;
                    $percentage = $data['percentage'] / 100;

                    $employees = Employee::with('activeContract')
                        ->whereIn('id', $data['employee_ids'])
                        ->get()
                        ->keyBy('id');

                    DB::transaction(function () use ($data, $employees, $percentage, &$count) {
                        foreach ($data['employee_ids'] as $employeeId) {
                            $employee = $employees->get($employeeId);

                            if (!$employee || !$employee->base_salary) {
                                continue;
                            }

                            $amount = round($employee->base_salary * $percentage, 0);

                            if ($amount <= 0) {
                                continue;
                            }

                            Loan::create([
                                'employee_id'       => $employeeId,
                                'type'              => 'advance',
                                'amount'            => $amount,
                                'installments_count' => 1,
                                'installment_amount' => $amount,
                                'status'            => 'pending',
                                'reason'            => $data['reason'],
                            ]);
                            $count++;
                        }
                    });

                    Notification::make()
                        ->title('Adelantos generados')
                        ->body("Se crearon {$count} adelantos en estado pendiente ({$data['percentage']}% del salario).")
                        ->success()
                        ->send();
                }),

            Action::make('export')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->form([
                    Select::make('status')
                        ->label('Estado')
                        ->options([
                            'pending'   => 'Pendientes',
                            'active'    => 'Activos',
                            'paid'      => 'Pagados',
                            'cancelled' => 'Cancelados',
                        ])
                        ->placeholder('Todos los estados')
                        ->multiple()
                        ->native(false),

                    Select::make('type')
                        ->label('Tipo')
                        ->options([
                            'loan'    => 'Préstamos',
                            'advance' => 'Adelantos',
                        ])
                        ->placeholder('Todos los tipos')
                        ->native(false),
                ])
                ->modalHeading('Exportar Préstamos / Adelantos')
                ->modalSubmitActionLabel('Exportar')
                ->action(function (array $data) {
                    return Excel::download(
                        new LoansExport($data['status'] ?? null, $data['type'] ?? null),
                        'prestamos_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                    );
                }),

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

            'cancelled' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'cancelled'))
                ->badge($counts['by_status']['cancelled'])
                ->badgeColor('danger'),
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
