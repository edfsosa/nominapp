<?php

namespace App\Filament\Resources\LoanResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use App\Filament\Resources\LoanResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Préstamo')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos')
                ->badge(fn() => $this->getModel()::count()),

            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'pending'))
                ->badge(fn() => $this->getModel()::where('status', 'pending')->count())
                ->badgeColor('warning'),

            'active' => Tab::make('Activos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'active'))
                ->badge(fn() => $this->getModel()::where('status', 'active')->count())
                ->badgeColor('info'),

            'paid' => Tab::make('Pagados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'paid'))
                ->badge(fn() => $this->getModel()::where('status', 'paid')->count())
                ->badgeColor('success'),

            'loans' => Tab::make('Préstamos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'loan'))
                ->badge(fn() => $this->getModel()::where('type', 'loan')->count())
                ->badgeColor('info')
                ->icon('heroicon-o-banknotes'),

            'advances' => Tab::make('Adelantos')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('type', 'advance'))
                ->badge(fn() => $this->getModel()::where('type', 'advance')->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-clock'),
        ];
    }
}
