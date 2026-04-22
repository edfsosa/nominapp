<?php

namespace App\Filament\Resources\MerchandiseWithdrawalResource\Pages;

use App\Filament\Resources\MerchandiseWithdrawalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

/** Página de listado de retiros de mercadería con tabs por estado. */
class ListMerchandiseWithdrawals extends ListRecords
{
    protected static string $resource = MerchandiseWithdrawalResource::class;

    /** @var array<string, int>|null */
    protected ?array $statusCounts = null;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Tabs para filtrar por estado.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->getStatusCounts();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['all']),
            'pending' => Tab::make('Pendientes')
                ->badge($counts['pending'] ?: null)
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'pending')),
            'approved' => Tab::make('Aprobados')
                ->badge($counts['approved'] ?: null)
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'approved')),
            'paid' => Tab::make('Pagados')
                ->badge($counts['paid'] ?: null)
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'paid')),
            'cancelled' => Tab::make('Cancelados')
                ->badge($counts['cancelled'] ?: null)
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'cancelled')),
        ];
    }

    /**
     * Retorna conteos por estado, cacheados para evitar N+1.
     *
     * @return array<string, int>
     */
    private function getStatusCounts(): array
    {
        if ($this->statusCounts === null) {
            $rows = \App\Models\MerchandiseWithdrawal::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $this->statusCounts = [
                'all' => array_sum($rows),
                'pending' => $rows['pending'] ?? 0,
                'approved' => $rows['approved'] ?? 0,
                'paid' => $rows['paid'] ?? 0,
                'cancelled' => $rows['cancelled'] ?? 0,
            ];
        }

        return $this->statusCounts;
    }
}
