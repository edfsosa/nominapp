<?php

namespace App\Filament\Resources\MerchandiseWithdrawalResource\RelationManagers;

use App\Models\MerchandiseWithdrawalInstallment;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

/** Tabla de cuotas de un retiro de mercadería (solo lectura). */
class InstallmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'installments';

    protected static ?string $title = 'Cuotas';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    /**
     * Infolist para visualizar el detalle de una cuota en el modal ViewAction.
     */
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Group::make([
                    TextEntry::make('installment_number')
                        ->label('Cuota #'),

                    TextEntry::make('amount')
                        ->label('Monto')
                        ->money('PYG', locale: 'es_PY'),

                    TextEntry::make('status')
                        ->label('Estado')
                        ->formatStateUsing(fn (string $state) => MerchandiseWithdrawalInstallment::getStatusLabel($state))
                        ->color(fn (string $state) => MerchandiseWithdrawalInstallment::getStatusColor($state))
                        ->icon(fn (string $state) => MerchandiseWithdrawalInstallment::getStatusIcon($state))
                        ->badge(),
                ])->columns(3),

                Group::make([
                    TextEntry::make('due_date')
                        ->label('Fecha de Vencimiento')
                        ->date('d/m/Y')
                        ->icon('heroicon-o-calendar'),

                    TextEntry::make('paid_at')
                        ->label('Fecha de Pago')
                        ->dateTime('d/m/Y H:i')
                        ->icon('heroicon-o-check-circle')
                        ->placeholder('No pagada'),
                ])->columns(2),

                TextEntry::make('notes')
                    ->label('Notas')
                    ->placeholder('Sin notas')
                    ->columnSpanFull(),
            ])
            ->columns(1);
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
                    ->color(fn (MerchandiseWithdrawalInstallment $record) => $record->isOverdue() ? 'danger' : null)
                    ->icon(fn (MerchandiseWithdrawalInstallment $record) => $record->isOverdue() ? 'heroicon-o-exclamation-triangle' : null),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => MerchandiseWithdrawalInstallment::getStatusLabel($state))
                    ->color(fn (string $state): string => MerchandiseWithdrawalInstallment::getStatusColor($state))
                    ->icon(fn (string $state): string => MerchandiseWithdrawalInstallment::getStatusIcon($state))
                    ->sortable(),

                TextColumn::make('paid_at')
                    ->label('Pagado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(MerchandiseWithdrawalInstallment::getStatusOptions())
                    ->native(false),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->withFilename('cuotas_retiro_'.$this->ownerRecord->id.'_'.now()->format('Y_m_d_H_i_s').'.xlsx'),
                    ])
                    ->label('Exportar a Excel')
                    ->color('info')
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->defaultSort('installment_number', 'asc')
            ->paginated(false)
            ->emptyStateHeading('Sin cuotas generadas')
            ->emptyStateDescription('Las cuotas se generan automáticamente al aprobar el retiro.')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
