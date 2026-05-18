<?php

namespace App\Filament\Resources\DisbursementBatchResource\Pages;

use App\Filament\Resources\DisbursementBatchResource;
use App\Models\DisbursementBatch;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/** Listado de lotes de pago bancario con tabs por estado. */
class ListDisbursementBatches extends ListRecords
{
    protected static string $resource = DisbursementBatchResource::class;

    /** @var array<string, mixed>|null Cache de conteos para badges de tabs. */
    protected ?array $batchCounts = null;

    /**
     * Obtiene conteos por estado para los badges de tabs (cacheado para evitar N+1).
     *
     * @return array<string, mixed>
     */
    protected function getBatchCounts(): array
    {
        if ($this->batchCounts === null) {
            $counts = DisbursementBatch::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $this->batchCounts = [
                'total' => array_sum($counts),
                'by_status' => $counts,
            ];
        }

        return $this->batchCounts;
    }

    /**
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->getBatchCounts();
        $byStatus = $counts['by_status'];

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['total']),

            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge($byStatus['pending'] ?? 0)
                ->badgeColor('warning'),

            'confirmed' => Tab::make('Confirmados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'confirmed'))
                ->badge($byStatus['confirmed'] ?? 0)
                ->badgeColor('success'),

            'partially_confirmed' => Tab::make('Parcialmente confirmados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'partially_confirmed'))
                ->badge($byStatus['partially_confirmed'] ?? 0)
                ->badgeColor('info'),

            'cancelled' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled'))
                ->badge($byStatus['cancelled'] ?? 0)
                ->badgeColor('gray'),
        ];
    }

    /** Pestaña activa por defecto. */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
