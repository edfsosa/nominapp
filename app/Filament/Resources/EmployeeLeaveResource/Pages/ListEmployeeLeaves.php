<?php

namespace App\Filament\Resources\EmployeeLeaveResource\Pages;

use App\Filament\Resources\EmployeeLeaveResource;
use App\Models\EmployeeLeave;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

/** Página de listado de licencias de empleados con tabs por estado. */
class ListEmployeeLeaves extends ListRecords
{
    protected static string $resource = EmployeeLeaveResource::class;

    /** @var array<string, int>|null Conteos por estado, calculados una sola vez por ciclo Livewire. */
    protected ?array $leaveCounts = null;

    /**
     * Devuelve los conteos de licencias agrupados por estado usando una sola query GROUP BY.
     *
     * @return array<string, int>
     */
    protected function getLeaveCounts(): array
    {
        if ($this->leaveCounts === null) {
            $counts = EmployeeLeave::query()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $this->leaveCounts = [
                'all' => array_sum($counts),
                'pending' => $counts['pending'] ?? 0,
                'approved' => $counts['approved'] ?? 0,
                'rejected' => $counts['rejected'] ?? 0,
            ];
        }

        return $this->leaveCounts;
    }

    /**
     * Devuelve las acciones del encabezado de la página.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Licencia')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Define los tabs de filtrado por estado para el listado.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->getLeaveCounts();

        return [
            'all' => Tab::make('Todas')
                ->badge($counts['all'])
                ->badgeColor('gray')
                ->icon('heroicon-o-document-text'),

            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge($counts['pending'])
                ->badgeColor('warning')
                ->icon('heroicon-o-clock'),

            'approved' => Tab::make('Aprobadas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved'))
                ->badge($counts['approved'])
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle'),

            'rejected' => Tab::make('Rechazadas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'rejected'))
                ->badge($counts['rejected'])
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
