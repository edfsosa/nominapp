<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use App\Models\Branch;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListBranches extends ListRecords
{
    protected static string $resource = BranchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Sucursal')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        $allCount = Branch::count();
        $withEmployeesCount = Branch::has('employees')->count();
        $withoutEmployeesCount = Branch::doesntHave('employees')->count();

        return [
            'all' => Tab::make('Todas')
                ->badge($allCount)
                ->badgeColor('gray')
                ->icon('heroicon-o-building-office-2'),

            'with_employees' => Tab::make('Con empleados')
                ->modifyQueryUsing(fn(Builder $query) => $query->has('employees'))
                ->badge($withEmployeesCount)
                ->badgeColor('success')
                ->icon('heroicon-o-users'),

            'without_employees' => Tab::make('Sin empleados')
                ->modifyQueryUsing(fn(Builder $query) => $query->doesntHave('employees'))
                ->badge($withoutEmployeesCount)
                ->badgeColor('warning')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
