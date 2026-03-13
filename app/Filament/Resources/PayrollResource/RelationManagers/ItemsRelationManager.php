<?php

namespace App\Filament\Resources\PayrollResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Detalle de Nómina';
    protected static ?string $modelLabel = 'ítem';
    protected static ?string $pluralModelLabel = 'ítems';

    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->status !== 'draft';
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('type')
                    ->label('Tipo')
                    ->options([
                        'perception' => 'Percepción',
                        'deduction'  => 'Deducción',
                    ])
                    ->native(false)
                    ->required()
                    ->live()
                    ->columnSpan(1),

                TextInput::make('description')
                    ->label('Descripción')
                    ->placeholder('Ejemplo: Bonificación por desempeño')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(1),

                TextInput::make('amount')
                    ->label('Monto')
                    ->numeric()
                    ->prefix('₲')
                    ->minValue(0)
                    ->maxValue(999999999.99)
                    ->step(0.01)
                    ->required()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn($state) => $state === 'perception' ? 'success' : 'danger')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'perception' => 'Percepción',
                        'deduction'  => 'Deducción',
                        default      => $state,
                    })
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->weight('bold')
                    ->color(fn($record) => $record->type === 'perception' ? 'success' : 'danger'),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'perception' => 'Percepciones',
                        'deduction'  => 'Deducciones',
                    ])
                    ->native(false),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar Ítem')
                    ->icon('heroicon-o-plus')
                    ->successNotificationTitle('Ítem agregado exitosamente')
                    ->visible(fn() => $this->getOwnerRecord()->status === 'draft'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->successNotificationTitle('Ítem actualizado exitosamente')
                    ->visible(fn() => $this->getOwnerRecord()->status === 'draft'),

                Tables\Actions\DeleteAction::make()
                    ->successNotificationTitle('Ítem eliminado exitosamente')
                    ->visible(fn() => $this->getOwnerRecord()->status === 'draft'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn() => $this->getOwnerRecord()->status === 'draft')
                        ->after(fn() => $this->recalculatePayrollTotals()),
                ]),
            ])
            ->emptyStateHeading('No hay ítems registrados')
            ->emptyStateDescription('Los ítems de percepciones y deducciones aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultGroup('type')
            ->groups([
                Tables\Grouping\Group::make('type')
                    ->label('Tipo')
                    ->getTitleFromRecordUsing(fn($record) => match ($record->type) {
                        'perception' => 'Percepciones',
                        'deduction' => 'Deducciones',
                        default => $record->type,
                    })
                    ->collapsible(),
            ]);
    }

    protected function afterCreate(): void
    {
        // Recalcular totales del recibo
        $this->recalculatePayrollTotals();
    }

    protected function afterUpdate(): void
    {
        // Recalcular totales del recibo
        $this->recalculatePayrollTotals();
    }

    protected function afterDelete(): void
    {
        // Recalcular totales del recibo
        $this->recalculatePayrollTotals();
    }

    protected function recalculatePayrollTotals(): void
    {
        $payroll = $this->getOwnerRecord();

        $totalPerceptions = $payroll->items()
            ->where('type', 'perception')
            ->sum('amount');

        $totalDeductions = $payroll->items()
            ->where('type', 'deduction')
            ->sum('amount');

        $grossSalary = $payroll->base_salary + $totalPerceptions;
        $netSalary = $grossSalary - $totalDeductions;

        $payroll->update([
            'total_perceptions' => $totalPerceptions,
            'total_deductions' => $totalDeductions,
            'gross_salary' => $grossSalary,
            'net_salary' => $netSalary,
        ]);
    }
}
