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
     * Obtiene conteos por tipo para los badges de tabs (cacheado para evitar N+1).
     *
     * @return array<string, mixed>
     */
    protected function getBatchCounts(): array
    {
        if ($this->batchCounts === null) {
            $counts = DisbursementBatch::query()
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type')
                ->toArray();

            $this->batchCounts = [
                'total' => array_sum($counts),
                'by_type' => $counts,
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
        $byType = $counts['by_type'];

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['total']),

            'advances' => Tab::make('Adelantos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'advances'))
                ->badge($byType['advances'] ?? 0)
                ->badgeColor('success'),

            'payroll' => Tab::make('Planilla')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'payroll'))
                ->badge($byType['payroll'] ?? 0)
                ->badgeColor('info'),

            'loan' => Tab::make('Préstamos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'loan'))
                ->badge($byType['loan'] ?? 0)
                ->badgeColor('warning'),

            'aguinaldo' => Tab::make('Aguinaldo')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'aguinaldo'))
                ->badge($byType['aguinaldo'] ?? 0)
                ->badgeColor('primary'),
        ];
    }

    /** Pestaña activa por defecto. */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
