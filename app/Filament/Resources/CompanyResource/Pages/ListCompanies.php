<?php

namespace App\Filament\Resources\CompanyResource\Pages;

use App\Exports\CompaniesExport;
use App\Filament\Resources\CompanyResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;

    /**
     * Define las acciones que se mostrarán en el encabezado de la página de listado de empresas.
     * @return array Un array de acciones que se mostrarán en el encabezado.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Exportar Empresas a Excel?')
                ->modalDescription('Se incluirán todas las empresas en un archivo Excel descargable.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    Notification::make()
                        ->success()
                        ->title('Exportación lista')
                        ->body('El listado de empresas se está descargando.')
                        ->send();

                    return Excel::download(
                        new CompaniesExport(),
                        'empresas_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                    );
                }),

            CreateAction::make()
                ->label('Nueva Empresa')
                ->icon('heroicon-o-plus'),
        ];
    }
}
