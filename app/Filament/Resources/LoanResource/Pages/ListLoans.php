<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Models\Loan;
use App\Models\Employee;
use App\Models\LoanInstallment;
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
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\OverdueInstallmentsExport;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

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
                    TextInput::make('amount')
                        ->label('Monto del Adelanto')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->prefix('Gs.')
                        ->placeholder('Ej: 500000'),

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

                            // Empleados mensuales activos sin adelanto activo/pendiente
                            return Employee::where('status', 'active')
                                ->where('payroll_type', 'monthly')
                                ->whereNotIn('id', $employeesWithActiveAdvance)
                                ->get()
                                ->mapWithKeys(fn($employee) => [
                                    $employee->id => "{$employee->full_name} - CI: {$employee->ci}"
                                ]);
                        })
                        ->helperText('Solo se muestran empleados mensuales activos sin adelanto pendiente/activo'),

                    Textarea::make('reason')
                        ->label('Motivo')
                        ->placeholder('Adelanto mensual')
                        ->maxLength(255)
                        ->nullable()
                        ->default('Adelanto mensual')
                        ->rows(1),
                ])
                ->modalHeading('Generar Adelantos Mensuales')
                ->modalDescription('Se crearán adelantos en estado pendiente para los empleados seleccionados.')
                ->modalSubmitActionLabel('Generar Adelantos')
                ->action(function (array $data): void {
                    $count = 0;

                    foreach ($data['employee_ids'] as $employeeId) {
                        Loan::create([
                            'employee_id' => $employeeId,
                            'type' => 'advance',
                            'amount' => $data['amount'],
                            'installments_count' => 1,
                            'installment_amount' => $data['amount'],
                            'status' => 'pending',
                            'reason' => $data['reason'] ?? 'Adelanto mensual',
                        ]);
                        $count++;
                    }

                    Notification::make()
                        ->title('Adelantos generados')
                        ->body("Se crearon {$count} adelantos en estado pendiente.")
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
                ->label('Nuevo Préstamo')
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
        return [
            'all' => Tab::make('Todos')
                ->badge(fn() => $this->getModel()::count()),

            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending'))
                ->badge(fn() => $this->getModel()::where('status', 'pending')->count())
                ->badgeColor('warning'),

            'active' => Tab::make('Activos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'active'))
                ->badge(fn() => $this->getModel()::where('status', 'active')->count())
                ->badgeColor('info'),

            'paid' => Tab::make('Pagados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'paid'))
                ->badge(fn() => $this->getModel()::where('status', 'paid')->count())
                ->badgeColor('success'),

            'loans' => Tab::make('Préstamos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'loan'))
                ->badge(fn() => $this->getModel()::where('type', 'loan')->count())
                ->badgeColor('info')
                ->icon('heroicon-o-banknotes'),

            'advances' => Tab::make('Adelantos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'advance'))
                ->badge(fn() => $this->getModel()::where('type', 'advance')->count())
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
