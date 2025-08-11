<?php

namespace App\Filament\Resources\BranchResource\RelationManagers;

use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('ci')
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
                    ->badge()
                    ->colors([
                        'primary' => 'monthly',
                        'secondary' => 'biweekly',
                        'info' => 'weekly',
                    ])
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employment_type')
                    ->label('Tipo de Empleo')
                    ->badge()
                    ->colors([
                        'success' => 'full_time',
                        'warning' => 'day_laborer',
                    ])
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('base_salary')
                    ->label('Salario base (₲)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('daily_rate')
                    ->label('Tarifa Diaria (₲)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->label('Método de Pago')
                    ->badge()
                    ->colors([
                        'primary' => 'debit',
                        'secondary' => 'cash',
                        'info' => 'check',
                    ])
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
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                        'warning' => 'suspended',
                    ])
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
