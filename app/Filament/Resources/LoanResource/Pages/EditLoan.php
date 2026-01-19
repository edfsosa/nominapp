<?php

namespace App\Filament\Resources\LoanResource\Pages;

use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Textarea;
use App\Filament\Resources\LoanResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Forms\Components\DatePicker;

class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activate')
                ->label('Activar')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn() => $this->record->isPending())
                ->requiresConfirmation()
                ->modalHeading('Activar Préstamo')
                ->modalDescription(fn() => "Se generarán {$this->record->installments_count} cuotas de " . number_format($this->record->installment_amount, 0, ',', '.') . " Gs. cada una.")
                ->form([
                    DatePicker::make('start_date')
                        ->label('Fecha de primera cuota')
                        ->default(now()->addMonth()->startOfMonth())
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->required()
                        ->helperText('Las cuotas siguientes serán el mismo día de cada mes'),
                ])
                ->action(function (array $data) {
                    $startDate = \Carbon\Carbon::parse($data['start_date']);
                    $result = $this->record->activate(Auth::id(), $startDate);

                    Notification::make()
                        ->success()
                        ->title('Préstamo Activado')
                        ->body($result['message'])
                        ->send();

                    $this->refreshFormData(['status', 'granted_at', 'granted_by_id']);
                }),

            Action::make('cancel')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn() => $this->record->isPending() || ($this->record->isActive() && $this->record->paid_installments_count === 0))
                ->requiresConfirmation()
                ->modalHeading('Cancelar Préstamo')
                ->modalDescription('¿Está seguro de que desea cancelar este préstamo?')
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo de cancelación')
                        ->placeholder('Ingrese el motivo...')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $result = $this->record->cancel($data['reason'] ?? null);

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Préstamo Cancelado')
                            ->body($result['message'])
                            ->send();

                        $this->refreshFormData(['status', 'notes']);
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['message'])
                            ->send();
                    }
                }),

            /* Action::make('mark_defaulted')
                ->label('Marcar Incobrable')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->visible(fn() => $this->record->isActive())
                ->requiresConfirmation()
                ->modalHeading('Marcar como Incobrable')
                ->modalDescription('Esta acción marcará el préstamo como incobrable. Las cuotas pendientes serán canceladas.')
                ->form([
                    Textarea::make('reason')
                        ->label('Motivo')
                        ->placeholder('Ingrese el motivo por el cual el préstamo es incobrable...')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $result = $this->record->markAsDefaulted($data['reason']);

                    Notification::make()
                        ->success()
                        ->title('Préstamo Marcado como Incobrable')
                        ->body($result['message'])
                        ->send();

                    $this->refreshFormData(['status', 'notes']);
                }), */

            Action::make('export_pdf')
                ->label('Exportar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->action(function () {
                    $this->record->load(['employee.position.department', 'grantedBy', 'installments']);

                    $pdf = Pdf::loadView('pdf.loan', ['loan' => $this->record])
                        ->setPaper('a4', 'portrait');

                    $type = $this->record->isLoan() ? 'prestamo' : 'adelanto';

                    return response()->streamDownload(function () use ($pdf) {
                        echo $pdf->output();
                    }, "{$type}_{$this->record->id}_{$this->record->employee->ci}.pdf");
                }),

            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn() => $this->record->isPending() || $this->record->isCancelled()),
        ];
    }
}
