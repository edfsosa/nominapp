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
    protected ?array $positionCounts = null;

    protected function getPositionCounts(): array
    {
        if ($this->positionCounts !== null) {
            return $this->positionCounts;
        }

        $withEmployees = Position::has('employees')->count();
        $total         = Position::count();

        $this->positionCounts = [
            'total'          => $total,
            'with_employees' => $withEmployees,
            'without'        => $total - $withEmployees,
        ];

        return $this->positionCounts;
    }

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
        $counts = $this->getPositionCounts();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['total'] ?: null),

            'with_employees' => Tab::make('Con Empleados')
                ->modifyQueryUsing(fn(Builder $query) => $query->has('employees'))
                ->badge($counts['with_employees'] ?: null)
                ->badgeColor('success'),

            'without_employees' => Tab::make('Sin Empleados')
                ->modifyQueryUsing(fn(Builder $query) => $query->doesntHave('employees'))
                ->badge($counts['without'] ?: null)
                ->badgeColor('gray'),
        ];
    }
}
