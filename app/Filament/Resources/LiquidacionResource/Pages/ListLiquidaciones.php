<?php

namespace App\Filament\Resources\LiquidacionResource\Pages;

use App\Filament\Resources\LiquidacionResource;
use App\Models\Liquidacion;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListLiquidaciones extends ListRecords
{
    protected static string $resource = LiquidacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Liquidación')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todas')
                ->badge(Liquidacion::count()),

            'draft' => Tab::make('Borradores')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'draft'))
                ->badge(Liquidacion::where('status', 'draft')->count())
                ->badgeColor('gray'),

            'calculated' => Tab::make('Calculadas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'calculated'))
                ->badge(Liquidacion::where('status', 'calculated')->count())
                ->badgeColor('warning'),

            'closed' => Tab::make('Cerradas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'closed'))
                ->badge(Liquidacion::where('status', 'closed')->count())
                ->badgeColor('success'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
