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
        $companies = Company::active()->orderBy('name')->get();

        $total = PayrollPeriod::count();
        $tabs = ['all' => Tab::make('Todos')->badge($total)];

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
        } else {
            $counts = PayrollPeriod::selectRaw('
                SUM(status = "draft") as draft,
                SUM(status = "processing") as processing,
                SUM(status = "closed") as closed
            ')->first();

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
        }

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'all';
    }
}
