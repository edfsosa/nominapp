<?php

namespace App\Filament\Resources\DisbursementBatchResource\RelationManagers;

use App\Models\Advance;
use App\Models\DisbursementBatch;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Muestra los adelantos incluidos en el lote y permite agregar, remover y marcar rechazos bancarios.
 */
class AdvancesRelationManager extends RelationManager
{
    protected static string $relationship = 'advances';

    protected static ?string $title = 'Adelantos del lote';

    protected static ?string $modelLabel = 'adelanto';

    protected static ?string $pluralModelLabel = 'adelantos';

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
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'employee',
                        fn ($q) => $q->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                    )),

                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('bank_account')
                    ->label('Cuenta bancaria')
                    ->getStateUsing(fn (Advance $record) => $record->employee?->bankAccounts->first()?->account_number)
                    ->placeholder('Sin cuenta')
                    ->badge()
                    ->color(fn (Advance $record) => $record->employee?->bankAccounts->first() ? 'gray' : 'danger')
                    ->icon(fn (Advance $record) => $record->employee?->bankAccounts->first() ? 'heroicon-o-building-library' : 'heroicon-o-exclamation-triangle'),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Advance::getStatusLabel($state))
                    ->color(fn (string $state) => Advance::getStatusColor($state)),

                TextColumn::make('bank_rejection_reason')
                    ->label('Rechazo bancario')
                    ->formatStateUsing(fn (?string $state) => Advance::getBankRejectionReasonLabel($state))
                    ->badge()
                    ->color('danger')
                    ->placeholder('-'),
            ])
            ->headerActions([
                Action::make('add_advances')
                    ->label('Agregar adelantos')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn () => $batch->isPending())
                    ->mountUsing(function (\Filament\Forms\Form $form, Action $action) use ($batch) {
                        $hasAvailable = Advance::query()
                            ->where('status', 'approved')
                            ->where('payment_method', 'transfer')
                            ->whereNull('disbursement_batch_id')
                            ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $batch->company_id))
                            ->exists();

                        if (! $hasAvailable) {
                            Notification::make()
                                ->warning()
                                ->title('Sin adelantos disponibles')
                                ->body('No hay adelantos aprobados por transferencia sin lote asignado para esta empresa.')
                                ->send();

                            $action->halt();

                            return;
                        }

                        $form->fill();
                    })
                    ->modalHeading('Agregar adelantos al lote')
                    ->modalSubmitActionLabel('Agregar')
                    ->form([
                        Select::make('advance_ids')
                            ->label('Adelantos disponibles')
                            ->multiple()
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function () use ($batch) {
                                return Advance::query()
                                    ->where('status', 'approved')
                                    ->where('payment_method', 'transfer')
                                    ->whereNull('disbursement_batch_id')
                                    ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $batch->company_id))
                                    ->with('employee')
                                    ->orderBy('created_at', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn (Advance $advance) => [
                                        $advance->id => $advance->employee->full_name
                                            .' — Gs. '.number_format((float) $advance->amount, 0, ',', '.'),
                                    ])
                                    ->toArray();
                            })
                            ->helperText('Solo se muestran adelantos aprobados por transferencia sin lote asignado.')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) use ($batch) {
                        $updated = Advance::whereIn('id', $data['advance_ids'])
                            ->where('status', 'approved')
                            ->where('payment_method', 'transfer')
                            ->whereNull('disbursement_batch_id')
                            ->update(['disbursement_batch_id' => $batch->id]);

                        Notification::make()
                            ->success()
                            ->title('Adelantos agregados')
                            ->body("Se agregaron {$updated} ".($updated === 1 ? 'adelanto' : 'adelantos').' al lote.')
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('remove_from_batch')
                    ->label('Remover')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Advance $record) => $batch->isPending() && $record->isApproved())
                    ->requiresConfirmation()
                    ->modalHeading('Remover adelanto del lote')
                    ->modalDescription(fn (Advance $record) => 'El adelanto de Gs. '.number_format((float) $record->amount, 0, ',', '.')." de {$record->employee->full_name} quedará disponible para asignarse a otro lote.")
                    ->modalSubmitActionLabel('Sí, remover')
                    ->action(function (Advance $record) {
                        $record->update(['disbursement_batch_id' => null]);

                        Notification::make()
                            ->warning()
                            ->title('Adelanto removido')
                            ->body('El adelanto fue removido del lote y quedó disponible.')
                            ->send();
                    }),

                Action::make('mark_rejected')
                    ->label('Marcar Rechazado')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Advance $record) => $batch->isPending() && $record->isApproved())
                    ->modalHeading('Marcar adelanto como rechazado por el banco')
                    ->modalDescription(fn (Advance $record) => 'El adelanto de '.number_format((float) $record->amount, 0, ',', '.').' Gs. de '.$record->employee->full_name.' volverá a estado Aprobado con la razón de rechazo indicada.')
                    ->modalSubmitActionLabel('Marcar rechazado')
                    ->form([
                        Select::make('bank_rejection_reason')
                            ->label('Motivo de rechazo')
                            ->options(Advance::getBankRejectionReasonOptions())
                            ->required()
                            ->native(false),
                    ])
                    ->action(function (Advance $record, array $data) {
                        $record->update([
                            'disbursement_batch_id' => null,
                            'bank_rejection_reason' => $data['bank_rejection_reason'],
                        ]);

                        Notification::make()
                            ->warning()
                            ->title('Adelanto rechazado')
                            ->body('El adelanto fue removido del lote y quedó en estado Aprobado.')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('Sin adelantos en este lote')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
