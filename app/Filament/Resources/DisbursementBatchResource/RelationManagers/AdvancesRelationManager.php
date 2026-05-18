<?php

namespace App\Filament\Resources\DisbursementBatchResource\RelationManagers;

use App\Models\Advance;
use App\Models\DisbursementBatch;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Muestra los adelantos incluidos en el lote y permite marcar rechazos bancarios.
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
            ->actions([
                Action::make('mark_rejected')
                    ->label('Marcar Rechazado')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Advance $record) => $batch->isPending() && $record->isApproved())
                    ->modalHeading('Marcar adelanto como rechazado por el banco')
                    ->modalDescription(fn (Advance $record) => 'El adelanto de '.number_format((float) $record->amount, 0, ',', '.').' Gs. de '.$record->employee->full_name.' volverá a estado Aprobado con la razón de rechazo indicada.')
                    ->modalSubmitActionLabel('Marcar rechazado')
                    ->form([
                        \Filament\Forms\Components\Select::make('bank_rejection_reason')
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
