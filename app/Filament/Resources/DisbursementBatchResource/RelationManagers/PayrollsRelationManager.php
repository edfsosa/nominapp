<?php

namespace App\Filament\Resources\DisbursementBatchResource\RelationManagers;

use App\Filament\Resources\PayrollResource;
use App\Models\DisbursementBatch;
use App\Models\Payroll;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup as TableActionGroup;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/**
 * Muestra los recibos de nómina incluidos en el lote y permite agregar, remover y marcar rechazos bancarios.
 */
class PayrollsRelationManager extends RelationManager
{
    protected static string $relationship = 'payrolls';

    protected static ?string $title = 'Recibos del lote';

    protected static ?string $modelLabel = 'recibo';

    protected static ?string $pluralModelLabel = 'recibos';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->type === 'payroll';
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
                'employee.activeContract.position',
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

                TextColumn::make('employee.activeContract.position.name')
                    ->label('Cargo')
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('bank_account')
                    ->label('Cuenta bancaria')
                    ->getStateUsing(fn (Payroll $record) => $record->employee?->bankAccounts->first()?->account_number)
                    ->placeholder('Sin cuenta')
                    ->badge()
                    ->color(fn (Payroll $record) => $record->employee?->bankAccounts->first() ? 'gray' : 'danger')
                    ->icon(fn (Payroll $record) => $record->employee?->bankAccounts->first() ? 'heroicon-o-building-library' : 'heroicon-o-exclamation-triangle'),

                TextColumn::make('net_salary')
                    ->label('Neto a pagar')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable()
                    ->weight('bold')
                    ->color('success')
                    ->summarize([
                        Sum::make()
                            ->money('PYG', locale: 'es_PY')
                            ->label('Total a acreditar'),
                    ]),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => Payroll::getStatusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => Payroll::getStatusColors()[$state] ?? 'gray')
                    ->icon(fn (string $state) => Payroll::getStatusIcons()[$state] ?? null),

                TextColumn::make('bank_rejection_reason')
                    ->label('Rechazo bancario')
                    ->formatStateUsing(fn (?string $state) => Payroll::getBankRejectionReasonLabel($state))
                    ->badge()
                    ->color('danger')
                    ->placeholder('-'),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename(fn () => 'recibos_lote_'.$batch->id.'_'.now()->format('d_m_Y_H_i_s'))
                            ->withWriterType(Excel::XLSX),
                    ])
                    ->label('Exportar a Excel')
                    ->color('info')
                    ->icon('heroicon-o-arrow-down-tray'),

                Action::make('add_payrolls')
                    ->label('Agregar recibos')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->visible(fn () => $batch->isPending())
                    ->mountUsing(function (\Filament\Forms\Form $form, Action $action) use ($batch) {
                        $hasAvailable = Payroll::query()
                            ->where('status', 'approved')
                            ->where('payment_method', 'transfer')
                            ->whereNull('disbursement_batch_id')
                            ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $batch->company_id))
                            ->exists();

                        if (! $hasAvailable) {
                            Notification::make()
                                ->warning()
                                ->title('Sin recibos disponibles')
                                ->body('No hay recibos aprobados por transferencia sin lote asignado para esta empresa.')
                                ->send();

                            $action->halt();

                            return;
                        }

                        $form->fill();
                    })
                    ->modalHeading('Agregar recibos al lote')
                    ->modalSubmitActionLabel('Agregar')
                    ->form([
                        Select::make('payroll_ids')
                            ->label('Recibos disponibles')
                            ->multiple()
                            ->native(false)
                            ->searchable()
                            ->preload()
                            ->required()
                            ->options(function () use ($batch) {
                                return Payroll::query()
                                    ->where('status', 'approved')
                                    ->where('payment_method', 'transfer')
                                    ->whereNull('disbursement_batch_id')
                                    ->whereHas('employee.branch', fn ($q) => $q->where('company_id', $batch->company_id))
                                    ->with(['employee', 'period'])
                                    ->orderBy('created_at', 'desc')
                                    ->get()
                                    ->mapWithKeys(fn (Payroll $payroll) => [
                                        $payroll->id => $payroll->employee->full_name
                                            .' — '.($payroll->period?->name ?? 'Sin período')
                                            .' — Gs. '.number_format((float) $payroll->net_salary, 0, ',', '.'),
                                    ])
                                    ->toArray();
                            })
                            ->helperText('Solo se muestran recibos aprobados por transferencia sin lote asignado.')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data) use ($batch) {
                        $updated = Payroll::whereIn('id', $data['payroll_ids'])
                            ->where('status', 'approved')
                            ->where('payment_method', 'transfer')
                            ->whereNull('disbursement_batch_id')
                            ->update(['disbursement_batch_id' => $batch->id]);

                        Notification::make()
                            ->success()
                            ->title('Recibos agregados')
                            ->body("Se agregaron {$updated} ".($updated === 1 ? 'recibo' : 'recibos').' al lote.')
                            ->send();
                    }),
            ])
            ->actions([
                Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(fn (Payroll $record) => PayrollResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),

                Action::make('download_pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->form([
                        Radio::make('mode')
                            ->label('Formato')
                            ->options([
                                'print' => 'Para imprimir — 2 copias en hoja horizontal',
                                'employee' => 'Para empleado — 1 copia en hoja vertical',
                            ])
                            ->default('print')
                            ->required(),
                    ])
                    ->modalHeading('Descargar Recibo PDF')
                    ->modalSubmitActionLabel('Descargar')
                    ->action(function (array $data, Payroll $record) {
                        $url = route('payrolls.download', ['payroll' => $record, 'mode' => $data['mode']]);
                        $this->js("window.open('{$url}', '_blank')");
                    }),

                TableActionGroup::make([
                    Action::make('remove_from_batch')
                        ->label('Remover del lote')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Payroll $record) => $batch->isPending() && $record->isApproved())
                        ->requiresConfirmation()
                        ->modalHeading('Remover recibo del lote')
                        ->modalDescription(fn (Payroll $record) => 'El recibo de '.$record->employee->full_name.' (Gs. '.number_format((float) $record->net_salary, 0, ',', '.').' neto) quedará disponible para asignarse a otro lote.')
                        ->modalSubmitActionLabel('Sí, remover')
                        ->action(function (Payroll $record) {
                            $record->update(['disbursement_batch_id' => null]);

                            Notification::make()
                                ->warning()
                                ->title('Recibo removido')
                                ->body('El recibo fue removido del lote y quedó disponible.')
                                ->send();
                        }),

                    Action::make('mark_rejected')
                        ->label('Marcar Rechazado')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn (Payroll $record) => $batch->isPending() && $record->isApproved())
                        ->modalHeading('Marcar recibo como rechazado por el banco')
                        ->modalDescription(fn (Payroll $record) => 'El recibo de '.$record->employee->full_name.' volverá a estado Aprobado con la razón de rechazo indicada.')
                        ->modalSubmitActionLabel('Marcar rechazado')
                        ->form([
                            Select::make('bank_rejection_reason')
                                ->label('Motivo de rechazo')
                                ->options(Payroll::getBankRejectionReasonOptions())
                                ->required()
                                ->native(false),
                        ])
                        ->action(function (Payroll $record, array $data) {
                            $record->update([
                                'disbursement_batch_id' => null,
                                'bank_rejection_reason' => $data['bank_rejection_reason'],
                            ]);

                            Notification::make()
                                ->warning()
                                ->title('Recibo rechazado')
                                ->body('El recibo fue removido del lote y quedó en estado Aprobado.')
                                ->send();
                        }),
                ]),
            ])
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Sin recibos en este lote')
            ->emptyStateDescription('Agregá recibos aprobados por transferencia usando el botón "Agregar recibos".')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
