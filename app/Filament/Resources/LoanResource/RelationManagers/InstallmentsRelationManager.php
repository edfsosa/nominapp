<?php

namespace App\Filament\Resources\LoanResource\RelationManagers;

use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\LoanInstallment;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Forms\Components\DateTimePicker;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use Filament\Resources\RelationManagers\RelationManager;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;

class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'Cuotas';

    protected static ?string $recordTitleAttribute = 'installment_number';

    /**
     * Función para definir el formulario de visualización de una cuota de préstamo.
     *
     * @param Form $form
     * @return Form
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('installment_number')
                    ->label('Número de Cuota')
                    ->disabled(),

                TextInput::make('amount')
                    ->label('Monto')
                    ->prefix('Gs.')
                    ->disabled(),

                DatePicker::make('due_date')
                    ->label('Fecha de Vencimiento')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->disabled(),

                Select::make('status')
                    ->label('Estado')
                    ->options(LoanInstallment::getStatusOptions())
                    ->disabled(),

                DateTimePicker::make('paid_at')
                    ->label('Fecha de Pago')
                    ->native(false)
                    ->disabled()
                    ->visible(fn($record) => $record?->paid_at !== null),

                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Función para definir la tabla de cuotas de préstamo.
     *
     * @param Table $table
     * @return Table
     */
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('installment_number')
            ->columns([
                TextColumn::make('installment_number')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn(LoanInstallment $record) => $record->isOverdue() ? 'danger' : null)
                    ->icon(fn(LoanInstallment $record) => $record->isOverdue() ? 'heroicon-o-exclamation-triangle' : null),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => LoanInstallment::getStatusLabel($state))
                    ->color(fn(string $state): string => LoanInstallment::getStatusColor($state))
                    ->icon(fn(string $state): string => LoanInstallment::getStatusIcon($state))
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Pagado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->defaultSort('installment_number', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(LoanInstallment::getStatusOptions())
                    ->native(false),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->withFilename('cuotas_préstamo_' . $this->ownerRecord->id . '_' . now()->format('Y_m_d_H_i_s') . '.xlsx'),
                        ])
                        ->label('Exportar a Excel')
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ]);
    }
}
