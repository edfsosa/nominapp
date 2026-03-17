<?php

namespace App\Filament\Resources\AguinaldoPeriodResource\Pages;

use App\Exports\AguinaldoPeriodsExport;
use App\Filament\Resources\AguinaldoPeriodResource;
use App\Models\AguinaldoPeriod;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListAguinaldoPeriods extends ListRecords
{
    protected static string $resource = AguinaldoPeriodResource::class;
    protected ?array $periodCounts = null;

    /**
     * Obtiene los conteos de períodos para badges de tabs, con caching para evitar consultas repetidas.
     */
    protected function getPeriodCounts(): array
    {
        // Si ya se han calculado los conteos, retornarlos para evitar consultas adicionales
        if ($this->periodCounts !== null) {
            return $this->periodCounts;
        }

        // Realizar una consulta agrupada para obtener conteos por estado y total, evitando múltiples consultas
        $counts = AguinaldoPeriod::query()
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Obtener conteo total y conteo para el año actual
        $yearCount = AguinaldoPeriod::where('year', now()->year)->count();

        // Almacenar los conteos en la propiedad para uso posterior
        $this->periodCounts = [
            'total'        => array_sum($counts),
            'by_status'    => $counts,
            'current_year' => $yearCount,
        ];

        // Retornar los conteos calculados
        return $this->periodCounts;
    }

    /**
     * Define las acciones del encabezado.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Exportar')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('¿Exportar Períodos de Aguinaldo a Excel?')
                ->modalDescription('Se incluirán todos los períodos de aguinaldo en un archivo Excel descargable. Puedes filtrar por estado utilizando las pestañas para exportar solo los períodos que te interesen.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    Notification::make()
                        ->success()
                        ->title('Exportación lista')
                        ->body('La planilla de períodos de aguinaldo se está descargando.')
                        ->send();

                    return Excel::download(
                        new AguinaldoPeriodsExport(status: $this->activeTab !== 'all' ? $this->activeTab : null),
                        'periodos_aguinaldo_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                    );
                }),

            CreateAction::make()
                ->label('Nuevo Período')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Define las pestañas para filtrar los períodos por estado, mostrando badges con conteos.
     *
     * @return array
     */
    public function getTabs(): array
    {
        $counts = $this->getPeriodCounts();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['total'] ?: null),

            'draft' => Tab::make('Borradores')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'draft'))
                ->badge($counts['by_status']['draft'] ?? null)
                ->badgeColor('gray'),

            'processing' => Tab::make('En Proceso')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'processing'))
                ->badge($counts['by_status']['processing'] ?? null)
                ->badgeColor('warning'),

            'closed' => Tab::make('Cerrados')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'closed'))
                ->badge($counts['by_status']['closed'] ?? null)
                ->badgeColor('success'),
        ];
    }

    /**
     * Define la pestaña activa por defecto al cargar la página.
     *
     * @return string|int|null
     */
    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
