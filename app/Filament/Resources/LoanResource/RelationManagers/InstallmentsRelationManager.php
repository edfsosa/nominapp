<?php

namespace App\Filament\Resources\LoanResource\RelationManagers;

use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\LoanInstallment;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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

                TextColumn::make('employeeDeduction.id')
                    ->label('Deducción')
                    ->formatStateUsing(fn($state) => $state ? "#{$state}" : '-')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'gray'),
            ])
            ->defaultSort('installment_number', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(LoanInstallment::getStatusOptions())
                    ->native(false),
            ])
            ->headerActions([
                // No permitimos crear cuotas manualmente
            ])
            ->actions([
                ViewAction::make(),

                Action::make('mark_paid')
                    ->label('Marcar Pagada')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(LoanInstallment $record) => $record->isPending() && $record->loan->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Marcar Cuota como Pagada')
                    ->modalDescription(fn(LoanInstallment $record) => "Se creará una deducción de " . number_format($record->amount, 0, ',', '.') . " Gs. para el empleado.")
                    ->action(function (LoanInstallment $record) {
                        $result = $record->markAsPaid();

                        if ($result['success']) {
                            Notification::make()
                                ->success()
                                ->title('Cuota Pagada')
                                ->body($result['message'])
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($result['message'])
                                ->send();
                        }
                    }),

                Action::make('revert_payment')
                    ->label('Revertir Pago')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn(LoanInstallment $record) => $record->isPaid())
                    ->requiresConfirmation()
                    ->modalHeading('Revertir Pago de Cuota')
                    ->modalDescription('Se eliminará la deducción asociada y la cuota volverá a estado pendiente.')
                    ->action(function (LoanInstallment $record) {
                        $result = $record->revertPayment();

                        if ($result['success']) {
                            Notification::make()
                                ->success()
                                ->title('Pago Revertido')
                                ->body($result['message'])
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Error')
                                ->body($result['message'])
                                ->send();
                        }
                    }),
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
