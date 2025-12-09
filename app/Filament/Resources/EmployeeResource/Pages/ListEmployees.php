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

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Agregar empleado')
                ->icon('heroicon-o-plus-circle'),

            ExportAction::make()
                ->exports([
                    ExcelExport::make()
                        ->fromTable()
                        ->except([
                            'photo',
                            'face_descriptor',
                            'created_at',
                            'updated_at',
                        ])
                        ->withFilename(fn() => 'empleados_' . now()->format('d_m_Y_H_i_s'))
                        ->withWriterType(Excel::XLSX)
                ])
                ->label('Exportar')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->tooltip('Exportar listado de empleados'),
        ];
    }

    public function getTabs(): array
    {
        $allCount = Employee::count();
        $activeCount = Employee::query()->where('status', 'active')->count();
        $inactiveCount = Employee::query()->where('status', 'inactive')->count();
        $suspendedCount = Employee::query()->where('status', 'suspended')->count();
        $withFaceCount = Employee::query()->whereNotNull('face_descriptor')->count();
        $withoutFaceCount = Employee::query()->whereNull('face_descriptor')->count();

        return [
            'all' => Tab::make('Todos')
                ->badge($allCount)
                ->badgeColor('gray')
                ->icon('heroicon-o-users'),

            'active' => Tab::make('Activos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'active'))
                ->badge($activeCount)
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle'),

            'inactive' => Tab::make('Inactivos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'inactive'))
                ->badge($inactiveCount)
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle'),

            'suspended' => Tab::make('Suspendidos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'suspended'))
                ->badge($suspendedCount)
                ->badgeColor('warning')
                ->icon('heroicon-o-pause-circle'),

            'with_face' => Tab::make('Con Rostro')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNotNull('face_descriptor'))
                ->badge($withFaceCount)
                ->badgeColor('success')
                ->icon('heroicon-o-check-badge'),

            'without_face' => Tab::make('Sin Rostro')
                ->modifyQueryUsing(fn(Builder $query) => $query->whereNull('face_descriptor'))
                ->badge($withoutFaceCount)
                ->badgeColor('warning')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'active';
    }
}
