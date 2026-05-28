<?php

namespace App\Filament\Resources\PositionResource\RelationManagers;

use App\Exports\PositionEmployeesExport;
use App\Filament\Resources\EmployeeResource;
use App\Models\Employee;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

/**
 * RelationManager para listar los empleados asignados a un cargo.
 * Solo lectura — crear y editar empleados se hace desde el módulo Empleados.
 */
class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    protected static ?string $title = 'Empleados';

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Define la tabla de empleados del cargo con filtro por estado y exportación a Excel.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Employee $record) => EmployeeResource::getUrl('view', ['record' => $record]))
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => $record->avatar_url),

                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->description(fn (Employee $record) => 'CI: '.$record->ci)
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('ci', 'like', "%{$search}%")
                    )
                    ->sortable(['first_name', 'last_name'])
                    ->weight('medium'),

                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-storefront')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (Employee $record): string => $record->status_color)
                    ->formatStateUsing(fn (string $state): string => Employee::getStatusOptions()[$state] ?? $state)
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Employee::getStatusOptions())
                    ->native(false)
                    ->multiple(),
            ])
            ->headerActions([
                Action::make('export_excel')
                    ->label('Exportar')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('¿Exportar Empleados a Excel?')
                    ->modalDescription('Se exportarán todos los empleados con este cargo.')
                    ->modalSubmitActionLabel('Sí, exportar')
                    ->action(function () {
                        Notification::make()
                            ->success()
                            ->title('Exportación lista')
                            ->body('El listado de empleados se está descargando.')
                            ->send();

                        return Excel::download(
                            new PositionEmployeesExport($this->ownerRecord->id),
                            'empleados_cargo_'.now()->format('Y_m_d_H_i_s').'.xlsx'
                        );
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No hay empleados asignados a este cargo')
            ->emptyStateDescription('Los empleados con contrato activo en este cargo aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
