<?php

namespace App\Filament\Resources\DeductionResource\Pages;

use App\Filament\Resources\DeductionResource;
use App\Models\Deduction;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/** Página de listado de deducciones con exportación a Excel. */
class ListDeductions extends ListRecords
{
    protected static string $resource = DeductionResource::class;

    /**
     * Acciones del encabezado: exportar Excel y crear.
     *
     * @return array<int, mixed>
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
                        ->withFilename('deducciones-'.now()->format('Y-m-d'))
                        ->withWriterType(Excel::XLSX)
                        ->withColumns([
                            Column::make('code')->heading('Código'),
                            Column::make('name')->heading('Nombre'),
                            Column::make('type')->heading('Tipo')->formatStateUsing(
                                fn ($state) => Deduction::getTypeLabels()[$state] ?? $state
                            ),
                            Column::make('description')->heading('Descripción'),
                            Column::make('calculation')->heading('Cálculo')->formatStateUsing(
                                fn ($state) => match ($state) {
                                    'fixed' => 'Monto Fijo',
                                    'percentage' => 'Porcentaje del Salario',
                                    default => '-',
                                }
                            ),
                            Column::make('amount')->heading('Monto Fijo'),
                            Column::make('percent')->heading('Porcentaje (%)'),
                            Column::make('is_mandatory')->heading('Obligatorio')->formatStateUsing(
                                fn ($state) => $state ? 'Sí' : 'No'
                            ),
                            Column::make('is_active')->heading('Estado')->formatStateUsing(
                                fn ($state) => $state ? 'Activo' : 'Inactivo'
                            ),
                            Column::make('created_at')->heading('Fecha de Creación'),
                        ]),
                ]),

            CreateAction::make()
                ->label('Nueva Deducción')
                ->icon('heroicon-o-plus')
                ->successNotificationTitle('Deducción creada exitosamente'),
        ];
    }
}
