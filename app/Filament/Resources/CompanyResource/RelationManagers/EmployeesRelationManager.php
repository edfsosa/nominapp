<?php

namespace App\Filament\Resources\CompanyResource\RelationManagers;

use App\Filament\Resources\EmployeeResource;
use App\Models\Branch;
use App\Models\Employee;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Lista los empleados de la empresa (a través de sus sucursales), solo lectura. */
class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    protected static ?string $title = 'Empleados';

    public function isReadOnly(): bool
    {
        return true;
    }

    /**
     * Define la tabla para listar los empleados de la empresa.
     */
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Nombre')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->weight('medium')
                    ->icon('heroicon-o-user'),

                TextColumn::make('ci')
                    ->label('CI')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-storefront')
                    ->sortable(),

                TextColumn::make('activeContract.position.name')
                    ->label('Cargo')
                    ->icon('heroicon-o-briefcase')
                    ->placeholder('Sin cargo')
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
                    ->native(false),

                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn () => Branch::where('company_id', $this->ownerRecord->id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                    )
                    ->native(false),
            ])
            ->recordUrl(fn (Employee $record) => EmployeeResource::getUrl('view', ['record' => $record]))
            ->actions([])
            ->defaultSort('first_name')
            ->emptyStateHeading('No hay empleados registrados')
            ->emptyStateDescription('Los empleados se crean desde el módulo de Empleados.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
