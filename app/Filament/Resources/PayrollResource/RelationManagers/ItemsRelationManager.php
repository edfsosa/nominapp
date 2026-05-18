<?php

namespace App\Filament\Resources\PayrollResource\RelationManagers;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'Detalle de Nómina';
    protected static ?string $modelLabel = 'ítem';
    protected static ?string $pluralModelLabel = 'ítems';

    /**
     * Determina si la relación es de solo lectura, permitiendo editar los ítems solo cuando la nómina está en estado "draft" y bloqueando cualquier modificación cuando la nómina ha sido aprobada o pagada.
     *
     * @return boolean
     */
    public function isReadOnly(): bool
    {
        return $this->getOwnerRecord()->status !== 'draft';
    }

    /**
     * Define el formulario para crear y editar los ítems de la nómina, con campos para tipo (percepción o deducción), descripción y monto, y con validaciones adecuadas.
     *
     * @param Form $form
     * @return Form
     */
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

    /**
     * Define la tabla para mostrar los ítems de la nómina, agrupados por tipo (percepción o deducción) y con acciones condicionadas al estado de la nómina.
     * @return Table
     */
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
                CreateAction::make()
                    ->label('Agregar Ítem')
                    ->icon('heroicon-o-plus')
                    ->successNotificationTitle('Ítem agregado exitosamente')
                    ->visible(fn() => $this->getOwnerRecord()->status === 'draft'),
            ])
            ->actions([
                EditAction::make()
                    ->successNotificationTitle('Ítem actualizado exitosamente')
                    ->visible(fn() => $this->getOwnerRecord()->status === 'draft'),

                DeleteAction::make()
                    ->successNotificationTitle('Ítem eliminado exitosamente')
                    ->visible(fn() => $this->getOwnerRecord()->status === 'draft'),
            ])
            ->emptyStateHeading('No hay ítems registrados')
            ->emptyStateDescription('Los ítems de percepciones y deducciones aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->defaultGroup('type')
            ->groups([
                Group::make('type')
                    ->label('Tipo')
                    ->getTitleFromRecordUsing(fn($record) => match ($record->type) {
                        'perception' => 'Percepciones',
                        'deduction' => 'Deducciones',
                        default => $record->type,
                    })
                    ->collapsible(),
            ]);
    }

    /**
     * Recalcula los totales de la nómina después de crear un ítem
     *
     * @return void
     */
    protected function afterCreate(): void
    {
        $this->recalculatePayrollTotals();
    }

    /**
     * Recalcula los totales de la nómina después de actualizar un ítem
     *
     * @return void
     */
    protected function afterUpdate(): void
    {
        $this->recalculatePayrollTotals();
    }

    /**
     * Recalcula los totales de la nómina después de eliminar un ítem
     *
     * @return void
     */
    protected function afterDelete(): void
    {
        $this->recalculatePayrollTotals();
    }

    /**
     * Recalcula los totales de percepciones, deducciones, salario bruto y salario neto de la nómina
     *
     * @return void
     */
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
