<?php

namespace App\Filament\Resources\AbsentResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\AbsentResource;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class EditAbsent extends EditRecord
{
    protected static string $resource = AbsentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('justify')
                ->label('Justificar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn() => !$this->record->isJustified())
                ->requiresConfirmation()
                ->modalHeading(fn() => $this->record->isUnjustified()
                    ? 'Cambiar a Justificada'
                    : 'Justificar Ausencia')
                ->modalDescription(fn() => $this->record->isUnjustified()
                    ? '¿Está seguro? Esto eliminará la deducción generada previamente.'
                    : '¿Está seguro de que desea marcar esta ausencia como justificada?')
                ->form([
                    Textarea::make('review_notes')
                        ->label('Notas de revisión')
                        ->placeholder('Motivo de la justificación...')
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $result = $this->record->justify(Auth::id(), $data['review_notes'] ?? null);

                    Notification::make()
                        ->success()
                        ->title('Ausencia Justificada')
                        ->body($result['message'])
                        ->send();

                    $this->refreshFormData(['status', 'reviewed_at', 'reviewed_by_id', 'review_notes', 'employee_deduction_id']);
                }),

            Action::make('mark_unjustified')
                ->label('Marcar Injustificada')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn() => !$this->record->isUnjustified())
                ->requiresConfirmation()
                ->modalHeading(fn() => $this->record->isJustified()
                    ? 'Cambiar a Injustificada'
                    : 'Marcar como Injustificada')
                ->modalDescription(fn() => $this->record->isJustified()
                    ? 'Esto generará una deducción del salario del empleado.'
                    : 'Esto generará automáticamente una deducción del salario del empleado.')
                ->form([
                    Textarea::make('review_notes')
                        ->label('Notas de revisión')
                        ->placeholder('Motivo por el cual se marca como injustificada...')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $result = $this->record->markAsUnjustified(Auth::id(), $data['review_notes']);

                    Notification::make()
                        ->success()
                        ->title('Ausencia Marcada como Injustificada')
                        ->body($result['message'])
                        ->send();

                    $this->refreshFormData(['status', 'reviewed_at', 'reviewed_by_id', 'review_notes', 'employee_deduction_id']);
                }),

            DeleteAction::make()
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }
}
