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
            LiquidacionResource::getExcelExportAction(),
            CreateAction::make()
                ->label('Nueva Liquidación')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        $counts = Liquidacion::query()
            ->selectRaw("
                COUNT(*) as total,
                SUM(status = 'draft') as draft,
                SUM(status = 'calculated') as calculated,
                SUM(status = 'closed') as closed
            ")
            ->first();

        return [
            'all' => Tab::make('Todas')
                ->badge($counts->total),

            'draft' => Tab::make('Borradores')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'draft'))
                ->badge($counts->draft)
                ->badgeColor('gray'),

            'calculated' => Tab::make('Calculadas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'calculated'))
                ->badge($counts->calculated)
                ->badgeColor('warning'),

            'closed' => Tab::make('Cerradas')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'closed'))
                ->badge($counts->closed)
                ->badgeColor('success'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
