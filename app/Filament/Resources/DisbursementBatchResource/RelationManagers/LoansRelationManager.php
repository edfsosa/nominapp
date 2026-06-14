<?php

namespace App\Filament\Resources\DisbursementBatchResource\RelationManagers;

use App\Models\DisbursementBatch;
use App\Models\Loan;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Muestra los préstamos incluidos en el lote y permite agregar, remover y marcar rechazos bancarios. */
class LoansRelationManager extends RelationManager
{
    protected static string $relationship = 'loans';

    protected static ?string $title = 'Préstamos del lote';

    protected static ?string $modelLabel = 'préstamo';

    protected static ?string $pluralModelLabel = 'préstamos';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->type === 'loan';
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        /** @var DisbursementBatch $batch */
        $batch = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'employee.bankAccounts' => fn ($q) => $q->where('is_primary', true)->where('status', 'active'),
            ]))
            ->columns([
                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('CI copiado')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->sortable()
                    ->wrap()
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'employee',
                        fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                    )),

                TextColumn::make('bank_account')
                    ->label('Cuenta bancaria')
                    ->getStateUsing(fn (Loan $record) => $record->employee?->bankAccounts->first()?->account_number)
                    ->placeholder('Sin cuenta')
                    ->badge()
                    ->color(fn (Loan $record) => $record->employee?->bankAccounts->first() ? 'gray' : 'danger')
                    ->icon(fn (Loan $record) => $record->employee?->bankAccounts->first() ? 'heroicon-o-building-library' : 'heroicon-o-exclamation-triangle'),

                TextColumn::make('amount')
                    ->label('Monto del préstamo')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total a acreditar'),
                    ]),

                TextColumn::make('installments_count')
                    ->label('Cuotas')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Loan::getStatusLabel($state))
                    ->color(fn (string $state) => Loan::getStatusColor($state)),
            ])
            ->headerActions([
                Action::make('add_loans')
                    ->label('Agregar préstamos')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn () => $batch->isPending())
                    ->mountUsing(function (\Filament\Forms\Form $form, Action $action) use ($batch) {
                        $hasAvailable = Loan::query()
                            ->where('status', 'approved')
                            ->where('payment_method', 'transfer')
                            ->whereNull('disbursement_batch_id')
                            ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $batch->company_id))
                            ->exists();

                        if (! $hasAvailable) {
                            Notification::make()
                                ->warning()
                                ->title('Sin préstamos disponibles')
                                ->body('No hay préstamos aprobados por transferencia sin lote asignado para esta empresa.')
                                ->send();

                            $action->halt();

                            return;
                        }

                        $form->fill();
                    })
                    ->modalHeading('Agregar préstamos al lote')
                    ->modalSubmitActionLabel('Agregar')
                    ->form([
                        Select::make('loan_ids')
                            ->label('Préstamos disponibles')
                            ->multiple()
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function () use ($batch) {
                                return Loan::query()
                                    ->where('status', 'approved')
                                    ->where('payment_method', 'transfer')
                                    ->whereNull('disbursement_batch_id')
                                    ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $batch->company_id))
                                    ->with('employee')
                                    ->orderBy('created_at', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn (Loan $loan) => [
                                        $loan->id => $loan->employee->full_name
                                            .' — Gs. '.number_format((float) $loan->amount, 0, ',', '.'),
                                    ])
                                    ->toArray();
                            })
                            ->helperText('Solo se muestran préstamos aprobados por transferencia sin lote asignado.')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) use ($batch) {
                        $updated = Loan::whereIn('id', $data['loan_ids'])
                            ->where('status', 'approved')
                            ->where('payment_method', 'transfer')
                            ->whereNull('disbursement_batch_id')
                            ->update(['disbursement_batch_id' => $batch->id]);

                        Notification::make()
                            ->success()
                            ->title('Préstamos agregados')
                            ->body("Se agregaron {$updated} ".($updated === 1 ? 'préstamo' : 'préstamos').' al lote.')
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('remove_from_batch')
                    ->label('Remover')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Loan $record) => $batch->isPending() && $record->isApproved())
                    ->requiresConfirmation()
                    ->modalHeading('Remover préstamo del lote')
                    ->modalDescription(fn (Loan $record) => 'El préstamo de Gs. '.number_format((float) $record->amount, 0, ',', '.')." de {$record->employee->full_name} quedará disponible para asignarse a otro lote.")
                    ->modalSubmitActionLabel('Sí, remover')
                    ->action(function (Loan $record) {
                        $record->update(['disbursement_batch_id' => null]);

                        Notification::make()
                            ->warning()
                            ->title('Préstamo removido')
                            ->body('El préstamo fue removido del lote y quedó disponible.')
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Sin préstamos en este lote')
            ->emptyStateDescription('Agregá préstamos aprobados por transferencia usando el botón "Agregar préstamos".')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
