<?php

namespace App\Filament\Resources\PayrollPeriodResource\Pages;

use App\Filament\Resources\PayrollPeriodResource;
use App\Models\Company;
use App\Models\PayrollPeriod;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPayrollPeriods extends ListRecords
{
    protected static string $resource = PayrollPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva Planilla')
                ->icon('heroicon-o-plus'),
        ];
    }

    public function getTabs(): array
    {
        // Una query para conteos por estado/frecuencia + una query para conteos por empresa.
        $counts = PayrollPeriod::selectRaw('
            COUNT(*) as total,
            SUM(status = "draft") as draft,
            SUM(status = "processing") as processing,
            SUM(status = "closed") as closed,
            SUM(frequency = "monthly") as monthly,
            SUM(frequency = "biweekly") as biweekly,
            SUM(frequency = "weekly") as weekly
        ')->first();

        $tabs = [
            'all' => Tab::make('Todos')->badge($counts->total),
        ];

        // Tabs por empresa cuando hay más de una activa.
        $companies = Company::active()->orderBy('name')->get();

        if ($companies->count() > 1) {
            $companyCounts = PayrollPeriod::selectRaw('company_id, COUNT(*) as total')
                ->groupBy('company_id')
                ->pluck('total', 'company_id');

            foreach ($companies as $company) {
                $tabs["company_{$company->id}"] = Tab::make($company->name)
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('company_id', $company->id))
                    ->badge($companyCounts[$company->id] ?? 0)
                    ->icon('heroicon-o-building-office-2');
            }
        }

        $tabs['draft'] = Tab::make('Borradores')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'draft'))
            ->badge($counts->draft)
            ->badgeColor('gray');

        $tabs['processing'] = Tab::make('En Proceso')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing'))
            ->badge($counts->processing)
            ->badgeColor('warning');

        $tabs['closed'] = Tab::make('Cerrados')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'closed'))
            ->badge($counts->closed)
            ->badgeColor('success');

        $tabs['monthly'] = Tab::make('Mensuales')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('frequency', 'monthly'))
            ->badge($counts->monthly)
            ->badgeColor('info');

        $tabs['biweekly'] = Tab::make('Quincenales')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('frequency', 'biweekly'))
            ->badge($counts->biweekly)
            ->badgeColor('info');

        $tabs['weekly'] = Tab::make('Semanales')
            ->modifyQueryUsing(fn (Builder $query) => $query->where('frequency', 'weekly'))
            ->badge($counts->weekly)
            ->badgeColor('info');

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
