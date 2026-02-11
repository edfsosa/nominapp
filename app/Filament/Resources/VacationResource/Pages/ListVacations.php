<?php

namespace App\Filament\Resources\VacationResource\Pages;

use App\Filament\Resources\VacationResource;
use App\Models\Vacation;
use App\Models\VacationBalance;
use App\Services\VacationService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\MaxWidth;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListVacations extends ListRecords
{
    protected static string $resource = VacationResource::class;

    /**
     * Define las acciones del encabezado de la página.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewBalances')
                ->label('Ver Balances')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalHeading('Balances de Vacaciones ' . now()->year)
                ->modalDescription('Resumen de días de vacaciones por empleado para el año actual.')
                ->modalContent(function () {
                    $balances = VacationBalance::where('year', now()->year)
                        ->with('employee')
                        ->orderBy('employee_id')
                        ->get();

                    return view('filament.resources.vacation-resource.pages.balances-modal', [
                        'balances' => $balances,
                        'year' => now()->year,
                    ]);
                })
                ->modalWidth(MaxWidth::FiveExtraLarge)
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar'),

            Action::make('generateBalances')
                ->label('Generar Balances')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Generar Balances de Vacaciones')
                ->modalDescription('Se generarán los balances de vacaciones para todos los empleados activos que aún no tengan balance en el año seleccionado.')
                ->form([
                    Select::make('year')
                        ->label('Año')
                        ->options(function () {
                            $currentYear = now()->year;
                            return [
                                $currentYear - 1 => $currentYear - 1,
                                $currentYear => $currentYear . ' (actual)',
                                $currentYear + 1 => $currentYear + 1,
                            ];
                        })
                        ->default(now()->year)
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data) {
                    $result = VacationService::generateBalancesForYear($data['year']);

                    Notification::make()
                        ->title('Balances generados')
                        ->body("Se crearon {$result['created']} balances. Se omitieron {$result['skipped']} que ya existían.")
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Nueva Solicitud')
                ->icon('heroicon-o-plus'),

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
                ->label('Exportar a Excel')
                ->color('info')
                ->icon('heroicon-o-arrow-down-tray')
                ->tooltip('Exportar listado de vacaciones'),
        ];
    }
}
