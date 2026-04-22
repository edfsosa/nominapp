<?php

namespace App\Filament\Resources\BranchResource\RelationManagers;

use App\Exports\BranchEmployeesExport;
use App\Models\Employee;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Facades\Excel;

/**
 * RelationManager para gestionar la relación de empleados asignados a una sucursal.
 * Permite visualizar, filtrar por estado y exportar a Excel los empleados de la sucursal.
 */
class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    protected static ?string $title = 'Empleados';

    public function isReadOnly(): bool
    {
        return false;
    }

    /**
     * Define la tabla de empleados con filtro por estado y exportación a Excel.
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn (Employee $record): string => $record->first_name.' '.$record->last_name)
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),

                TextColumn::make('full_name')
                    ->label('Nombre completo')
                    ->getStateUsing(fn (Employee $record) => $record->first_name.' '.$record->last_name)
                    ->description(fn (Employee $record) => 'CI: '.$record->ci)
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('activeContract.position.name')
                    ->label('Cargo')
                    ->icon('heroicon-o-briefcase')
                    ->description(fn (Employee $record) => $record->activeContract?->position?->department?->name)
                    ->placeholder('—')
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'suspended' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'suspended' => 'Suspendido',
                        default => $state,
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'suspended' => 'Suspendido',
                    ])
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
                    ->modalDescription('Se exportarán todos los empleados de esta sucursal.')
                    ->modalSubmitActionLabel('Sí, exportar')
                    ->action(function () {
                        Notification::make()
                            ->success()
                            ->title('Exportación lista')
                            ->body('El listado de empleados se está descargando.')
                            ->send();

                        return Excel::download(
                            new BranchEmployeesExport($this->ownerRecord->id),
                            'empleados_sucursal_'.now()->format('Y_m_d_H_i_s').'.xlsx'
                        );
                    }),
            ])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading('No hay empleados en esta sucursal')
            ->emptyStateDescription('Los empleados asignados a esta sucursal aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
