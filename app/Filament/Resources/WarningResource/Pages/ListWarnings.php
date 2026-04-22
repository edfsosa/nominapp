<?php

namespace App\Filament\Resources\WarningResource\Pages;

use App\Exports\WarningsExport;
use App\Filament\Resources\WarningResource;
use App\Models\Warning;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

/** Página de listado de amonestaciones con tabs por tipo. */
class ListWarnings extends ListRecords
{
    protected static string $resource = WarningResource::class;

    /** @var array<string, mixed>|null Cache de conteos para badges de tabs. */
    protected ?array $warningCounts = null;

    /**
     * Obtiene conteos por tipo para los badges de tabs (cacheado para evitar N+1).
     *
     * @return array<string, mixed>
     */
    protected function getWarningCounts(): array
    {
        if ($this->warningCounts === null) {
            $counts = Warning::query()
                ->selectRaw('type, count(*) as total')
                ->groupBy('type')
                ->pluck('total', 'type')
                ->toArray();

            $this->warningCounts = [
                'total' => array_sum($counts),
                'by_type' => $counts,
            ];
        }

        return $this->warningCounts;
    }

    /**
     * Define las acciones del encabezado de la página.
     *
     * @return array<int, mixed>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Exportar Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Exportar Amonestaciones')
                ->modalDescription('Se exportarán todas las amonestaciones registradas a un archivo Excel.')
                ->modalSubmitActionLabel('Exportar')
                ->action(function () {
                    Notification::make()
                        ->title('Exportación iniciada')
                        ->body('El archivo se descargará en un momento.')
                        ->success()
                        ->send();

                    return Excel::download(
                        new WarningsExport,
                        'amonestaciones_'.now()->format('Y_m_d_H_i_s').'.xlsx'
                    );
                }),

            CreateAction::make()
                ->label('Nueva Amonestación')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Define las pestañas de filtrado por tipo.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->getWarningCounts();
        $byType = $counts['by_type'];

        return [
            'all' => Tab::make('Todas')
                ->badge($counts['total']),

            'verbal' => Tab::make('Verbales')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'verbal'))
                ->badge($byType['verbal'] ?? 0)
                ->badgeColor('warning'),

            'written' => Tab::make('Escritas')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'written'))
                ->badge($byType['written'] ?? 0)
                ->badgeColor('danger'),

            'severe' => Tab::make('Graves')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', 'severe'))
                ->badge($byType['severe'] ?? 0)
                ->badgeColor('danger'),
        ];
    }

    /**
     * Pestaña activa por defecto.
     */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
