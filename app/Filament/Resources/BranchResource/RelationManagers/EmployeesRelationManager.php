<?php

namespace App\Filament\Resources\BranchResource\RelationManagers;

use App\Models\Employee;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';
    protected static ?string $title = 'Empleados';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitle(fn(Employee $record): string => $record->first_name . ' ' . $record->last_name)
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),

                TextColumn::make('full_name')
                    ->label('Nombre completo')
                    ->getStateUsing(fn(Employee $record) => $record->first_name . ' ' . $record->last_name)
                    ->description(fn(Employee $record) => 'CI: ' . $record->ci)
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('position.name')
                    ->label('Cargo')
                    ->description(fn(Employee $record) => $record->position?->department?->name)
                    ->sortable()
                    ->searchable(),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->formatStateUsing(fn($state) => $state ? '+595 ' . $state : null)
                    ->icon('heroicon-o-phone')
                    ->url(fn(Employee $record) => $record->phone ? 'https://api.whatsapp.com/send?phone=595' . $record->phone : null)
                    ->openUrlInNewTab()
                    ->placeholder('Sin teléfono'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'suspended' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
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
                ExportAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except(['photo', 'face_descriptor'])
                            ->withFilename('empleados_sucursal_' . now()->format('d_m_Y_H_i_s'))
                            ->withWriterType(Excel::XLSX),
                    ])
                    ->label('Exportar a Excel')
                    ->color('info')
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->actions([])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except(['photo', 'face_descriptor'])
                            ->withFilename('empleados_sucursal_' . now()->format('d_m_Y_H_i_s'))
                            ->withWriterType(Excel::XLSX),
                    ])
                    ->label('Exportar a Excel')
                    ->color('info')
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->emptyStateHeading('No hay empleados en esta sucursal')
            ->emptyStateDescription('Los empleados asignados a esta sucursal aparecerán aquí')
            ->emptyStateIcon('heroicon-o-users');
    }
}
