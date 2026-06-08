<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Pages\EmployeeReport;
use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Define las acciones del encabezado de la página de listado.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('go_to_report')
                ->label('Ver Reporte')
                ->icon('heroicon-o-chart-bar')
                ->color('gray')
                ->url(EmployeeReport::getUrl()),

            CreateAction::make()
                ->label('Nuevo Empleado')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Define las pestañas para filtrar los registros.
     */
    public function getTabs(): array
    {
        $counts = Employee::getTabCounts();
        $statusOptions = Employee::getStatusOptions();

        return [
            'all' => Tab::make('Todos')
                ->badge($counts['all'] ?: null)
                ->badgeColor('gray')
                ->icon('heroicon-o-users'),

            'active' => Tab::make($statusOptions['active'])
                ->modifyQueryUsing(fn (Builder $query) => $query->active())
                ->badge($counts['active'] ?: null)
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle'),

            'inactive' => Tab::make($statusOptions['inactive'])
                ->modifyQueryUsing(fn (Builder $query) => $query->inactive())
                ->badge($counts['inactive'] ?: null)
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle'),

            'suspended' => Tab::make($statusOptions['suspended'])
                ->modifyQueryUsing(fn (Builder $query) => $query->suspended())
                ->badge($counts['suspended'] ?: null)
                ->badgeColor('warning')
                ->icon('heroicon-o-pause-circle'),

            'weak_face' => Tab::make('Descriptor débil')
                ->modifyQueryUsing(fn (Builder $query) => $query->active()->withWeakFaceDescriptor())
                ->badge($counts['weak_face'] ?: null)
                ->badgeColor('danger')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    /**
     * Define la pestaña activa por defecto.
     */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }
}
