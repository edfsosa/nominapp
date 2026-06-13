<?php

namespace App\Filament\Resources\PayrollResource\Pages;

use App\Filament\Pages\SalaryReport;
use App\Filament\Resources\PayrollResource;
use App\Models\Payroll;
use Filament\Actions\Action;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPayrolls extends ListRecords
{
    protected static string $resource = PayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('go_to_report')
                ->label('Ver Reporte')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(SalaryReport::getUrl()),
        ];
    }

    public function getTabs(): array
    {
        // Una sola query para todos los badges.
        $counts = Payroll::selectRaw('
            COUNT(*) as total,
            SUM(status = "draft") as draft,
            SUM(status = "approved") as approved,
            SUM(status = "disbursed") as disbursed,
            SUM(status = "paid") as paid
        ')->first();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts->total),

            'draft' => Tab::make('Borradores')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
                ->badge($counts->draft)
                ->badgeColor('gray'),

            'approved' => Tab::make('Aprobados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved'))
                ->badge($counts->approved)
                ->badgeColor('warning'),

            'disbursed' => Tab::make('Acreditados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'disbursed'))
                ->badge($counts->disbursed)
                ->badgeColor('info'),

            'paid' => Tab::make('Pagados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'paid'))
                ->badge($counts->paid)
                ->badgeColor('success'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
