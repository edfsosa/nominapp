<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Exports\ContractsExport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ContractResource;
use Maatwebsite\Excel\Facades\Excel;

class ListContracts extends ListRecords
{
    protected static string $resource = ContractResource::class;

    /**
     * Define las acciones del encabezado: exportar a Excel y crear nuevo contrato.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Exportar contratos a Excel')
                ->modalDescription('Se descargará un archivo Excel con todos los contratos registrados en el sistema.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    Notification::make()
                        ->success()
                        ->title('Exportación iniciada')
                        ->body('El archivo se descargará en breve.')
                        ->send();

                    return Excel::download(new ContractsExport(), 'contratos_' . now()->format('Y_m_d_H_i_s') . '.xlsx');
                }),

            CreateAction::make()
                ->label('Nuevo Contrato')
                ->icon('heroicon-o-plus'),
        ];
    }
}
