<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EmployeeDeductionsRelationManager extends RelationManager
{
    protected static string $relationship = 'employeeDeductions';
    protected static ?string $title = 'Deducciones';
    protected static ?string $modelLabel = 'Deducción';
    protected static ?string $pluralModelLabel = 'Deducciones';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('deduction_id')
                    ->label('Deducción')
                    ->relationship('deduction', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn($state, callable $set) => $set('custom_amount', null)),

                DatePicker::make('start_date')
                    ->label('Fecha de inicio')
                    ->native(false)
                    ->default(now())
                    ->required()
                    ->maxDate(fn(Get $get) => $get('end_date')),

                DatePicker::make('end_date')
                    ->label('Fecha de fin')
                    ->native(false)
                    ->after('start_date')
                    ->minDate(fn(Get $get) => $get('start_date')),

                TextInput::make('custom_amount')
                    ->label('Monto personalizado')
                    ->numeric()
                    ->prefix('₲')
                    ->minValue(0)
                    ->maxValue(99999999.99)
                    ->helperText('Dejar vacío para usar el monto configurado en la deducción')
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
            ])
            ->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('deduction.code')
                    ->label('Código')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('deduction.name')
                    ->label('Deducción')
                    ->sortable()
                    ->searchable()
                    ->wrap(),

                TextColumn::make('deduction.calculation')
                    ->label('Tipo')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'fixed' => 'Fijo',
                        'percentage' => 'Porcentaje',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'fixed' => 'success',
                        'percentage' => 'info',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('amount_display')
                    ->label('Monto')
                    ->getStateUsing(function ($record) {
                        // Si hay monto personalizado, mostrarlo
                        if ($record->custom_amount !== null) {
                            return '₲ ' . number_format($record->custom_amount, 0, ',', '.');
                        }

                        // Si no, mostrar el monto de la deducción según su tipo
                        if ($record->deduction->calculation === 'percentage') {
                            return $record->deduction->percent . '%';
                        }

                        return '₲ ' . number_format($record->deduction->amount, 0, ',', '.');
                    })
                    ->badge()
                    ->color(fn($record) => $record->custom_amount !== null ? 'warning' : 'gray'),

                TextColumn::make('start_date')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->placeholder('Indefinido')
                    ->sortable(),

                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->notes)
                    ->toggleable(isToggledHiddenByDefault: true),

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
                SelectFilter::make('deduction_id')
                    ->label('Deducción')
                    ->relationship('deduction', 'name')
                    ->searchable()
                    ->preload(),

                Filter::make('activas')
                    ->label('Solo activas')
                    ->query(fn($query) => $query->where(function ($q) {
                        $q->where('end_date', '>=', now())
                            ->orWhereNull('end_date');
                    }))
                    ->default(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar deducción')
                    ->icon('heroicon-o-plus'),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar'),
                DeleteAction::make()
                    ->label('Eliminar'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No hay deducciones')
            ->emptyStateDescription('Comienza agregando una deducción al empleado')
            ->emptyStateIcon('heroicon-o-minus-circle');
    }
}
