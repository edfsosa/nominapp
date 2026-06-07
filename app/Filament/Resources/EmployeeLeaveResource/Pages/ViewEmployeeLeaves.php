<?php

namespace App\Filament\Resources\EmployeeLeaveResource\Pages;

use App\Filament\Resources\EmployeeLeaveResource;
use App\Models\Absence;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

/** Página de detalle de una licencia de empleado con acciones de aprobación y rechazo. */
class ViewEmployeeLeaves extends ViewRecord
{
    protected static string $resource = EmployeeLeaveResource::class;

    /**
     * Devuelve las acciones disponibles en el encabezado según el estado de la licencia.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar Licencia')
                ->modalDescription(function () {
                    $record = $this->record;
                    $count = Absence::where('employee_id', $record->employee_id)
                        ->whereHas('attendanceDay', fn ($q) => $q->whereBetween('date', [$record->start_date, $record->end_date]))
                        ->whereIn('status', ['pending', 'unjustified'])
                        ->count();

                    $base = 'Se aprobará la licencia del empleado.';
                    if ($count > 0) {
                        $base .= " Se justificarán automáticamente {$count} ausencia(s) registrada(s) en el período.";
                    }

                    return $base;
                })
                ->modalSubmitActionLabel('Sí, aprobar')
                ->visible(fn () => $this->record->status === 'pending')
                ->action(function () {
                    $result = $this->record->approve(Auth::id());

                    $body = $result['justified_count'] > 0
                        ? "Se justificaron {$result['justified_count']} ausencia(s) del período automáticamente."
                        : 'La licencia fue aprobada. No había ausencias pendientes en el período.';

                    Notification::make()
                        ->success()
                        ->title('Licencia aprobada')
                        ->body($body)
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('reject')
                ->label('Rechazar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Rechazar Licencia')
                ->modalDescription('Se rechazará esta solicitud de licencia.')
                ->modalSubmitActionLabel('Sí, rechazar')
                ->visible(fn () => $this->record->status === 'pending')
                ->action(function () {
                    $this->record->reject();

                    Notification::make()
                        ->warning()
                        ->title('Licencia rechazada')
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('primary'),
        ];
    }
}
