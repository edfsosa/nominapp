<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Carga el empleado con sus relaciones para evitar N+1 en el infolist.
     */
    protected function resolveRecord(int|string $key): Employee
    {
        return Employee::with([
            'branch.company',
            'activeContract.position.department',
        ])->findOrFail($key);
    }

    /**
     * Define las acciones disponibles en la vista de detalle del empleado.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_legajo')
                ->label('Descargar Legajo')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn () => route('employees.legajo', $this->record))
                ->openUrlInNewTab(),

            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('primary'),
        ];
    }
}
