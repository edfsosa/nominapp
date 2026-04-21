<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Exports\LoansExport;
use App\Filament\Resources\LoanResource;
use App\Models\Loan;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    /** @var array<string, mixed>|null Cache de conteos para badges de tabs. */
    protected ?array $loanCounts = null;

    /**
     * Obtiene conteos por estado para los badges de tabs (cacheado para evitar N+1).
     *
     * @return array<string, mixed>
     */
    protected function getLoanCounts(): array
    {
        if ($this->loanCounts === null) {
            $counts = Loan::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $this->loanCounts = [
                'total' => array_sum($counts),
                'by_status' => $counts,
            ];
        }

        return $this->loanCounts;
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
                ->modalHeading('Exportar Préstamos')
                ->modalSubmitActionLabel('Exportar')
                ->form([
                    Select::make('status')
                        ->label('Estado')
                        ->options(Loan::getStatusOptions())
                        ->placeholder('Todos los estados')
                        ->multiple()
                        ->native(false),
                ])
                ->action(function (array $data) {
                    Notification::make()
                        ->title('Exportación iniciada')
                        ->body('El archivo se descargará en un momento.')
                        ->success()
                        ->send();

                    return Excel::download(
                        new LoansExport($data['status'] ?? null),
                        'prestamos_'.now()->format('Y_m_d_H_i_s').'.xlsx'
                    );
                }),

            CreateAction::make()
                ->label('Nuevo Préstamo')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Define las pestañas de filtrado por estado.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $counts = $this->getLoanCounts();
        $byStatus = $counts['by_status'];

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['total']),

            'pending' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge($byStatus['pending'] ?? 0)
                ->badgeColor('warning'),

            'active' => Tab::make('Activos')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active'))
                ->badge($byStatus['active'] ?? 0)
                ->badgeColor('info'),

            'paid' => Tab::make('Pagados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid'))
                ->badge($byStatus['paid'] ?? 0)
                ->badgeColor('success'),

            'defaulted' => Tab::make('En Mora')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'defaulted'))
                ->badge($byStatus['defaulted'] ?? 0)
                ->badgeColor('danger'),

            'cancelled' => Tab::make('Cancelados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled'))
                ->badge($byStatus['cancelled'] ?? 0)
                ->badgeColor('gray'),
        ];
    }

    /**
     * Define la pestaña activa por defecto
     */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending';
    }
}
