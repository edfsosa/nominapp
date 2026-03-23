<?php

namespace App\Filament\Resources\ScheduleResource\Pages;

use App\Exports\SchedulesExport;
use App\Filament\Resources\ScheduleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

/** Página de listado de horarios con exportación a Excel. */
class ListSchedules extends ListRecords
{
    protected static string $resource = ScheduleResource::class;

    /**
     * Define las acciones del encabezado: exportar a Excel y crear nuevo horario.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Exportar Horarios a Excel?')
                ->modalDescription('Se exportarán todos los horarios con sus días activos y empleados asignados.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    Notification::make()
                        ->success()
                        ->title('Exportación lista')
                        ->body('El listado de horarios se está descargando.')
                        ->send();

                    return Excel::download(
                        new SchedulesExport(),
                        'horarios_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                    );
                }),

            CreateAction::make()
                ->label('Nuevo Horario')
                ->icon('heroicon-o-plus'),
        ];
    }
}
