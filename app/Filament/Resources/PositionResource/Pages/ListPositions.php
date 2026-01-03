<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Filament\Resources\PositionResource;
use App\Models\Position;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPositions extends ListRecords
{
    protected static string $resource = PositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Cargo')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos')
                ->badge(Position::count()),

            'with_employees' => Tab::make('Con Empleados')
                ->modifyQueryUsing(fn(Builder $query) => $query->has('employees'))
                ->badge(Position::has('employees')->count())
                ->badgeColor('success'),

            'without_employees' => Tab::make('Sin Empleados')
                ->modifyQueryUsing(fn(Builder $query) => $query->doesntHave('employees'))
                ->badge(Position::doesntHave('employees')->count())
                ->badgeColor('gray'),
        ];
    }
}
