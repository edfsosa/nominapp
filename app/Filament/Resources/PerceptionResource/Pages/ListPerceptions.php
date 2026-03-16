<?php

namespace App\Filament\Resources\PerceptionResource\Pages;

use App\Filament\Resources\PerceptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListPerceptions extends ListRecords
{
    protected static string $resource = PerceptionResource::class;

    /**
     * Obtiene las acciones que se mostrarán en el encabezado de la página de listado.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->exports([
                    ExcelExport::make()
                        ->withFilename('percepciones-' . now()->format('Y-m-d'))
                        ->withWriterType(Excel::XLSX)
                        ->withColumns([
                            Column::make('code')->heading('Código'),
                            Column::make('name')->heading('Nombre'),
                            Column::make('description')->heading('Descripción'),
                            Column::make('calculation')->heading('Tipo')->formatStateUsing(fn($state) => match ($state) {
                                'fixed'      => 'Monto Fijo',
                                'percentage' => 'Porcentaje del Salario',
                                default      => '-',
                            }),
                            Column::make('amount')->heading('Monto Fijo'),
                            Column::make('percent')->heading('Porcentaje (%)'),
                            Column::make('is_taxable')->heading('Gravable')->formatStateUsing(fn($state) => $state ? 'Sí' : 'No'),
                            Column::make('affects_ips')->heading('Afecta IPS')->formatStateUsing(fn($state) => $state ? 'Sí' : 'No'),
                            Column::make('affects_irp')->heading('Afecta IRP')->formatStateUsing(fn($state) => $state ? 'Sí' : 'No'),
                            Column::make('is_active')->heading('Estado')->formatStateUsing(fn($state) => $state ? 'Activo' : 'Inactivo'),
                            Column::make('created_at')->heading('Fecha de Creación'),
                        ]),
                ]),

            CreateAction::make()
                ->label('Nueva Percepción')
                ->icon('heroicon-o-plus')
                ->successNotificationTitle('Percepción creada exitosamente'),
        ];
    }
}
