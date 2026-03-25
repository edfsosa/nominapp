<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Exports\EmployeesExport;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Facades\Excel;
use App\Filament\Resources\EmployeeResource;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Define las acciones del encabezado de la página de listado.
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
                ->modalHeading('¿Exportar Empleados a Excel?')
                ->modalDescription('Se exportarán todos los empleados con sus datos personales y de contrato activo.')
                ->modalSubmitActionLabel('Sí, exportar')
                ->action(function () {
                    Notification::make()
                        ->success()
                        ->title('Exportación lista')
                        ->body('El listado de empleados se está descargando.')
                        ->send();

                    return Excel::download(
                        new EmployeesExport(),
                        'empleados_' . now()->format('Y_m_d_H_i_s') . '.xlsx'
                    );
                }),

            CreateAction::make()
                ->label('Nuevo Empleado')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Define las pestañas para filtrar los registros.
     *
     * @return array
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
                ->modifyQueryUsing(fn(Builder $query) => $query->active())
                ->badge($counts['active'] ?: null)
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle'),

            'inactive' => Tab::make($statusOptions['inactive'])
                ->modifyQueryUsing(fn(Builder $query) => $query->inactive())
                ->badge($counts['inactive'] ?: null)
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle'),

            'suspended' => Tab::make($statusOptions['suspended'])
                ->modifyQueryUsing(fn(Builder $query) => $query->suspended())
                ->badge($counts['suspended'] ?: null)
                ->badgeColor('warning')
                ->icon('heroicon-o-pause-circle'),

            'weak_face' => Tab::make('Descriptor débil')
                ->modifyQueryUsing(fn(Builder $query) => $query->withWeakFaceDescriptor())
                ->badge($counts['weak_face'] ?: null)
                ->badgeColor('danger')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    /**
     * Define la pestaña activa por defecto.
     *
     * @return string|int|null
     */
    public function getDefaultActiveTab(): string | int | null
    {
        return 'active';
    }
}
