<?php

namespace App\Filament\Resources\BranchResource\RelationManagers;

use App\Models\Employee;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
            ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'active'))
            ->recordTitle(fn(Employee $record): string => $record->first_name . ' ' . $record->last_name)
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular(),
                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('first_name')
                    ->label('Nombre(s)')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Apellido(s)')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->prefix('+595')
                    ->url(fn(Employee $record): ?string => $record->phone ? 'https://api.whatsapp.com/send?phone=595' . $record->phone : null)
                    ->openUrlInNewTab()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->url(fn(Employee $record): ?string => $record->email ? 'mailto:' . $record->email : null)
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hire_date')
                    ->label('Contratación')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payroll_type')
                    ->label('Tipo de Nómina')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'monthly' => 'Mensual',
                        'biweekly' => 'Quincenal',
                        'weekly' => 'Semanal',
                        default => ucfirst($state),
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employment_type')
                    ->label('Tipo de Empleo')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'full_time' => 'Tiempo Completo',
                        'day_laborer' => 'Jornalero',
                        default => ucfirst($state),
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->label('Método de Pago')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'debit' => 'Débito',
                        'cash' => 'Efectivo',
                        'check' => 'Cheque',
                        default => ucfirst($state),
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('position.name')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('position.department.name')
                    ->label('Departamento')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('schedule.name')
                    ->label('Horario')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'suspended' => 'warning',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'suspended' => 'Suspendido',
                        default => ucfirst($state),
                    })
                    ->sortable()
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                ExportAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except([
                                'photo',
                                'face_descriptor',
                                'created_at',
                                'updated_at',
                            ])
                            ->withFilename('empleados_' . now()->format('d_m_Y_H_i_s')),
                    ])
                    ->label('Exportar')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-tray')
            ])
            ->actions([])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except([
                                'photo',
                                'face_descriptor',
                                'created_at',
                                'updated_at',
                            ])
                            ->withFilename('empleados_' . now()->format('d_m_Y_H_i_s')),
                    ])
                    ->label('Exportar')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-tray')
            ]);
    }
}
