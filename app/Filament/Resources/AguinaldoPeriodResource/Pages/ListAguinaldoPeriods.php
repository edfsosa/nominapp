<?php

namespace App\Filament\Resources\AguinaldoPeriodResource\Pages;

use App\Filament\Resources\AguinaldoPeriodResource;
use App\Models\AguinaldoPeriod;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAguinaldoPeriods extends ListRecords
{
    protected static string $resource = AguinaldoPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo Período')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Todos')
                ->badge(AguinaldoPeriod::count()),

            'draft' => Tab::make('Borradores')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'draft'))
                ->badge(AguinaldoPeriod::where('status', 'draft')->count())
                ->badgeColor('gray'),

            'processing' => Tab::make('En Proceso')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'processing'))
                ->badge(AguinaldoPeriod::where('status', 'processing')->count())
                ->badgeColor('warning'),

            'closed' => Tab::make('Cerrados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'closed'))
                ->badge(AguinaldoPeriod::where('status', 'closed')->count())
                ->badgeColor('success'),

            'current_year' => Tab::make('Año ' . now()->year)
                ->modifyQueryUsing(fn(Builder $query) => $query->where('year', now()->year))
                ->badge(AguinaldoPeriod::where('year', now()->year)->count())
                ->badgeColor('info'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
