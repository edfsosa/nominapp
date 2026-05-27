<?php

namespace App\Filament\Resources\AbsenceResource\Pages;

use App\Filament\Resources\AbsenceResource;
use App\Models\Absence;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAbsences extends ListRecords
{
    protected static string $resource = AbsenceResource::class;

    /** @var array<string, int>|null */
    protected ?array $absenceCounts = null;

    protected function getHeaderActions(): array
    {
        return [
            AbsenceResource::getExcelExportAction(),

            CreateAction::make()
                ->label('Nueva Ausencia')
                ->icon('heroicon-o-plus'),
        ];
    }

    /** Retorna los counts por estado, calculados una sola vez por ciclo Livewire. */
    protected function getAbsenceCounts(): array
    {
        if ($this->absenceCounts === null) {
            $counts = Absence::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $this->absenceCounts = [
                'all' => array_sum($counts),
                'pending' => $counts['pending'] ?? 0,
                'justified' => $counts['justified'] ?? 0,
                'unjustified' => $counts['unjustified'] ?? 0,
            ];
        }

        return $this->absenceCounts;
    }

    public function getTabs(): array
    {
        $counts = $this->getAbsenceCounts();

        return [
            'all' => Tab::make('Todas')
                ->badge($counts['all']),

            'pending' => Tab::make('Pendientes')
                ->badge($counts['pending'])
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending')),

            'justified' => Tab::make('Justificadas')
                ->badge($counts['justified'])
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'justified')),

            'unjustified' => Tab::make('Injustificadas')
                ->badge($counts['unjustified'])
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'unjustified')),
        ];
    }
}
