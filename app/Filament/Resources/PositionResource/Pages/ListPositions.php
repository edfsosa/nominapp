<?php

namespace App\Filament\Resources\PositionResource\Pages;

use App\Exports\PositionsExport;
use App\Filament\Resources\PositionResource;
use App\Models\Position;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListPositions extends ListRecords
{
    protected static string $resource = PositionResource::class;
    protected ?array $positionCounts = null;

    /**
     * Obtiene los conteos de cargos para cada categoría y los almacena en caché para evitar consultas repetidas.
     * 
     * @return array
     */
    protected function getPositionCounts(): array
    {
        if ($this->positionCounts !== null) {
            return $this->positionCounts;
        }

        $withEmployees = Position::has('employees')->count();
        $total         = Position::count();

        $this->positionCounts = [
            'total'          => $total,
            'with_employees' => $withEmployees,
            'without'        => $total - $withEmployees,
        ];

        return $this->positionCounts;
    }

    /**
     * Define las acciones disponibles en el encabezado de la página.
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
                ->modalHeading('¿Exportar Cargos a Excel?')
                ->modalDescription('Se incluirán todos los cargos en un archivo Excel descargable.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    Notification::make()
                        ->success()
                        ->title('Exportación lista')
                        ->body('El listado de cargos se está descargando.')
                        ->send();

                    return Excel::download(
                        new PositionsExport(),
                        'cargos_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                    );
                }),

            CreateAction::make()
                ->label('Nuevo Cargo')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Define las pestañas para filtrar los cargos según su relación con empleados.
     * 
     * @return array
     */
    public function getTabs(): array
    {
        $counts = $this->getPositionCounts();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['total'] ?: null),

            'with_employees' => Tab::make('Con Empleados')
                ->modifyQueryUsing(fn(Builder $query) => $query->has('employees'))
                ->badge($counts['with_employees'] ?: null)
                ->badgeColor('success'),

            'without_employees' => Tab::make('Sin Empleados')
                ->modifyQueryUsing(fn(Builder $query) => $query->doesntHave('employees'))
                ->badge($counts['without'] ?: null)
                ->badgeColor('gray'),
        ];
    }
}
