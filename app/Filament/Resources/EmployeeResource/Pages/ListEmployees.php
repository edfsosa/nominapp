<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label('Todos')
                ->badge(Employee::count()),
            'active' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'active'))
                ->label('Activos')
                ->badge(Employee::query()->where('status', 'active')->count())
                ->badgeColor('success'),
            'inactive' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'inactive'))
                ->label('Inactivos')
                ->badge(Employee::query()->where('status', 'inactive')->count())
                ->badgeColor('danger'),
            'suspended' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'suspended'))
                ->label('Suspendidos')
                ->badge(Employee::query()->where('status', 'suspended')->count())
                ->badgeColor('warning'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'active';
    }
}
