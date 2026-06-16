<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AbsenceResource;
use App\Filament\Resources\AdvanceResource;
use App\Filament\Resources\EmployeeLeaveResource;
use App\Filament\Resources\LoanResource;
use App\Models\Absence;
use App\Models\Advance;
use App\Models\EmployeeLeave;
use App\Models\Loan;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Widget de aprobaciones pendientes: muestra elementos que requieren acción inmediata. */
class PendingApprovalsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $pollingInterval = '60s';

    /**
     * Retorna las tarjetas de pendientes por módulo.
     *
     * @return array<int, Stat>
     */
    protected function getStats(): array
    {
        $loansPending = Loan::where('status', 'pending')->count();
        $advancesPending = Advance::where('status', 'pending')->count();
        $absencesPending = Absence::where('status', 'pending')->count();
        $leavesPending = EmployeeLeave::where('status', 'pending')->count();

        return [
            Stat::make('Préstamos por Aprobar', $loansPending)
                ->description($loansPending === 0
                    ? 'Sin solicitudes pendientes'
                    : ($loansPending === 1 ? '1 solicitud en espera' : "{$loansPending} solicitudes en espera"))
                ->descriptionIcon($loansPending > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle')
                ->color($loansPending > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-banknotes')
                ->url(LoanResource::getUrl('index')),

            Stat::make('Adelantos por Aprobar', $advancesPending)
                ->description($advancesPending === 0
                    ? 'Sin adelantos pendientes'
                    : ($advancesPending === 1 ? '1 adelanto en espera' : "{$advancesPending} adelantos en espera"))
                ->descriptionIcon($advancesPending > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle')
                ->color($advancesPending > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-wallet')
                ->url(AdvanceResource::getUrl('index')),

            Stat::make('Ausencias por Revisar', $absencesPending)
                ->description($absencesPending === 0
                    ? 'Sin ausencias pendientes'
                    : ($absencesPending === 1 ? '1 ausencia por clasificar' : "{$absencesPending} ausencias por clasificar"))
                ->descriptionIcon($absencesPending > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle')
                ->color($absencesPending > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-user-minus')
                ->url(AbsenceResource::getUrl('index')),

            Stat::make('Licencias por Aprobar', $leavesPending)
                ->description($leavesPending === 0
                    ? 'Sin licencias pendientes'
                    : ($leavesPending === 1 ? '1 licencia por aprobar' : "{$leavesPending} licencias por aprobar"))
                ->descriptionIcon($leavesPending > 0 ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle')
                ->color($leavesPending > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-calendar-days')
                ->url(EmployeeLeaveResource::getUrl('index')),
        ];
    }
}
