<?php

namespace App\Filament\Resources\EmployeeLeaveResource\Pages;

use App\Filament\Resources\EmployeeLeaveResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListEmployeeLeaves extends ListRecords
{
    protected static string $resource = EmployeeLeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Agregar permiso')
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    public function getTabs(): array
    {
        $allCount = $this->getResource()::getModel()::count();
        $pendingCount = $this->getResource()::getModel()::where('status', 'pending')->count();
        $aprovedCount = $this->getResource()::getModel()::where('status', 'approved')->count();
        $rejectedCount = $this->getResource()::getModel()::where('status', 'rejected')->count();

        return [
            'all' => Tab::make('Todas')
                ->badge($allCount)
                ->badgeColor('gray')
                ->icon('heroicon-o-document-text'),

            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending'))
                ->badge($pendingCount)
                ->badgeColor('warning')
                ->icon('heroicon-o-clock'),

            'approved' => Tab::make('Aprobadas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'approved'))
                ->badge($aprovedCount)
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle'),

            'rejected' => Tab::make('Rechazadas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'rejected'))
                ->badge($rejectedCount)
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
