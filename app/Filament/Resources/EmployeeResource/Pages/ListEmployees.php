<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Define las acciones del encabezado de la página de listado.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Empleado')
                ->icon('heroicon-o-plus-circle'),

            ExportAction::make()
                ->exports([
                    ExcelExport::make()
                        ->fromTable()
                        ->except(['photo', 'face_descriptor', 'has_face'])
                        ->withFilename(fn() => 'empleados_' . now()->format('d_m_Y_H_i_s'))
                        ->withWriterType(Excel::XLSX)
                ])
                ->label('Exportar a Excel')
                ->color('info')
                ->icon('heroicon-o-arrow-down-tray')
                ->tooltip('Exportar listado de empleados'),
        ];
    }

    /**
     * Define las pestañas para filtrar los registros.
     *
     * @return array
     */
    public function getTabs(): array
    {
        $counts = Employee::getTabCounts();
        $statusOptions = Employee::getStatusOptions();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['all'])
                ->badgeColor('gray')
                ->icon('heroicon-o-users'),

            'active' => Tab::make($statusOptions['active'])
                ->modifyQueryUsing(fn(Builder $query) => $query->active())
                ->badge($counts['active'])
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle'),

            'inactive' => Tab::make($statusOptions['inactive'])
                ->modifyQueryUsing(fn(Builder $query) => $query->inactive())
                ->badge($counts['inactive'])
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle'),

            'suspended' => Tab::make($statusOptions['suspended'])
                ->modifyQueryUsing(fn(Builder $query) => $query->suspended())
                ->badge($counts['suspended'])
                ->badgeColor('warning')
                ->icon('heroicon-o-pause-circle'),

            'with_face' => Tab::make('Con Rostro')
                ->modifyQueryUsing(fn(Builder $query) => $query->withFace())
                ->badge($counts['with_face'])
                ->badgeColor('success')
                ->icon('heroicon-o-check-badge'),

            'without_face' => Tab::make('Sin Rostro')
                ->modifyQueryUsing(fn(Builder $query) => $query->withoutFace())
                ->badge($counts['without_face'])
                ->badgeColor('warning')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    /**
     * Define la pestaña activa por defecto.
     *
     * @return void
     */
    public function getDefaultActiveTab(): string | int | null
    {
        return 'active';
    }
}
