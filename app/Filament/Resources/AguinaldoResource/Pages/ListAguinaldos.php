<?php

namespace App\Filament\Resources\AguinaldoResource\Pages;

use App\Filament\Resources\AguinaldoResource;
use App\Models\Aguinaldo;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAguinaldos extends ListRecords
{
    protected static string $resource = AguinaldoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        $currentYear = now()->year;

        return [
            'all' => Tab::make('Todos')
                ->badge(Aguinaldo::count()),

            'current_year' => Tab::make("Año {$currentYear}")
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('period', fn($q) => $q->where('year', $currentYear)))
                ->badge(Aguinaldo::whereHas('period', fn($q) => $q->where('year', $currentYear))->count())
                ->badgeColor('info'),

            'previous_year' => Tab::make("Año " . ($currentYear - 1))
                ->modifyQueryUsing(fn(Builder $query) => $query->whereHas('period', fn($q) => $q->where('year', $currentYear - 1)))
                ->badge(Aguinaldo::whereHas('period', fn($q) => $q->where('year', $currentYear - 1))->count())
                ->badgeColor('gray'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'current_year';
    }
}
