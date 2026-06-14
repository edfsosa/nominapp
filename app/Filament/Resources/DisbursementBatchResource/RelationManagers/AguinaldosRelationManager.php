<?php

namespace App\Filament\Resources\DisbursementBatchResource\RelationManagers;

use App\Models\Aguinaldo;
use App\Models\DisbursementBatch;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Muestra los aguinaldos incluidos en el lote y permite agregar, remover y marcar rechazos bancarios. */
class AguinaldosRelationManager extends RelationManager
{
    protected static string $relationship = 'aguinaldos';

    protected static ?string $title = 'Aguinaldos del lote';

    protected static ?string $modelLabel = 'aguinaldo';

    protected static ?string $pluralModelLabel = 'aguinaldos';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->type === 'aguinaldo';
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
                'period',
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
                    ->getStateUsing(fn (Aguinaldo $record) => $record->employee?->bankAccounts->first()?->account_number)
                    ->placeholder('Sin cuenta')
                    ->badge()
                    ->color(fn (Aguinaldo $record) => $record->employee?->bankAccounts->first() ? 'gray' : 'danger')
                    ->icon(fn (Aguinaldo $record) => $record->employee?->bankAccounts->first() ? 'heroicon-o-building-library' : 'heroicon-o-exclamation-triangle'),

                TextColumn::make('aguinaldo_amount')
                    ->label('Monto aguinaldo')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total a acreditar'),
                    ]),

                TextColumn::make('months_worked')
                    ->label('Meses')
                    ->badge()
                    ->color('info')
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Aguinaldo::getStatusLabel($state))
                    ->color(fn (string $state) => Aguinaldo::getStatusColor($state)),
            ])
            ->headerActions([
                Action::make('add_aguinaldos')
                    ->label('Agregar aguinaldos')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn () => $batch->isPending())
                    ->mountUsing(function (\Filament\Forms\Form $form, Action $action) use ($batch) {
                        $hasAvailable = Aguinaldo::query()
                            ->where('status', 'pending')
                            ->where('payment_method', 'transfer')
                            ->whereNull('disbursement_batch_id')
                            ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $batch->company_id))
                            ->exists();

                        if (! $hasAvailable) {
                            Notification::make()
                                ->warning()
                                ->title('Sin aguinaldos disponibles')
                                ->body('No hay aguinaldos pendientes por transferencia sin lote asignado para esta empresa.')
                                ->send();

                            $action->halt();

                            return;
                        }

                        $form->fill();
                    })
                    ->modalHeading('Agregar aguinaldos al lote')
                    ->modalSubmitActionLabel('Agregar')
                    ->form([
                        Select::make('aguinaldo_ids')
                            ->label('Aguinaldos disponibles')
                            ->multiple()
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function () use ($batch) {
                                return Aguinaldo::query()
                                    ->where('status', 'pending')
                                    ->where('payment_method', 'transfer')
                                    ->whereNull('disbursement_batch_id')
                                    ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $batch->company_id))
                                    ->with('employee')
                                    ->orderBy('id')
                                    ->get()
                                    ->mapWithKeys(fn (Aguinaldo $a) => [
                                        $a->id => $a->employee->full_name
                                            .' — Gs. '.number_format((float) $a->aguinaldo_amount, 0, ',', '.'),
                                    ])
                                    ->toArray();
                            })
                            ->helperText('Solo se muestran aguinaldos pendientes por transferencia sin lote asignado.')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) use ($batch) {
                        $updated = Aguinaldo::whereIn('id', $data['aguinaldo_ids'])
                            ->where('status', 'pending')
                            ->where('payment_method', 'transfer')
                            ->whereNull('disbursement_batch_id')
                            ->update(['disbursement_batch_id' => $batch->id]);

                        Notification::make()
                            ->success()
                            ->title('Aguinaldos agregados')
                            ->body("Se agregaron {$updated} ".($updated === 1 ? 'aguinaldo' : 'aguinaldos').' al lote.')
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('remove_from_batch')
                    ->label('Remover')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn (Aguinaldo $record) => $batch->isPending() && $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Remover aguinaldo del lote')
                    ->modalDescription(fn (Aguinaldo $record) => 'El aguinaldo de Gs. '.number_format((float) $record->aguinaldo_amount, 0, ',', '.')." de {$record->employee->full_name} quedará disponible para asignarse a otro lote.")
                    ->modalSubmitActionLabel('Sí, remover')
                    ->action(function (Aguinaldo $record) {
                        $record->update(['disbursement_batch_id' => null]);

                        Notification::make()
                            ->warning()
                            ->title('Aguinaldo removido')
                            ->body('El aguinaldo fue removido del lote y quedó disponible.')
                            ->send();
                    }),
            ])
            ->bulkActions([])
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultSort('id', 'asc')
            ->emptyStateHeading('Sin aguinaldos en este lote')
            ->emptyStateDescription('Agregá aguinaldos pendientes por transferencia usando el botón "Agregar aguinaldos".')
            ->emptyStateIcon('heroicon-o-gift');
    }
}
