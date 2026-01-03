<?php

namespace App\Filament\Resources\VacationResource\Pages;

use App\Filament\Resources\VacationResource;
use App\Models\Vacation;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ManageVacations extends ManageRecords
{
    protected static string $resource = VacationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve_all_pending')
                ->label('Aprobar Pendientes')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar Todas las Solicitudes Pendientes')
                ->modalDescription('¿Está seguro de aprobar todas las solicitudes de vacaciones pendientes? Esta acción no se puede deshacer.')
                ->action(function () {
                    $updated = Vacation::where('status', 'pending')->update(['status' => 'approved']);

                    Notification::make()
                        ->title($updated > 0 ? "Se aprobaron $updated solicitudes pendientes" : 'No hay solicitudes pendientes')
                        ->success()
                        ->send();
                })
                ->visible(fn() => Vacation::where('status', 'pending')->exists()),

            Action::make('delete_old')
                ->label('Limpiar Antiguas')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Eliminar Vacaciones Antiguas')
                ->modalDescription('Esto eliminará todas las vacaciones finalizadas hace más de 2 años. Esta acción no se puede deshacer.')
                ->action(function () {
                    $twoYearsAgo = now()->subYears(2);
                    $deleted = Vacation::where('end_date', '<', $twoYearsAgo)->delete();

                    Notification::make()
                        ->title($deleted > 0 ? "Se eliminaron $deleted registros antiguos" : 'No hay registros antiguos para eliminar')
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Nueva Solicitud')
                ->icon('heroicon-o-plus')
                ->successNotificationTitle('Solicitud de vacaciones creada exitosamente'),

            ExportAction::make()
                ->exports([
                    ExcelExport::make()
                        ->fromTable()
                        ->except([
                            'created_at',
                            'updated_at',
                            'employee_id',
                        ])
                        ->withFilename(fn() => 'vacaciones_' . now()->format('d_m_Y_H_i_s'))
                        ->withWriterType(Excel::XLSX)
                ])
                ->label('Exportar')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->tooltip('Exportar listado de vacaciones')
        ];
    }
}
