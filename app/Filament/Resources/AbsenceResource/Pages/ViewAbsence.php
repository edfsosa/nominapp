<?php

namespace App\Filament\Resources\AbsenceResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\AbsenceResource;
use Illuminate\Support\Facades\Auth;

class ViewAbsence extends ViewRecord
{
    protected static string $resource = AbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('justify')
                ->label('Justificar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->tooltip('Marcar esta ausencia como justificada')
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

                    $this->record->refresh();
                }),

            Action::make('mark_unjustified')
                ->label('Marcar Injustificada')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->tooltip('Marcar como injustificada y generar deducción salarial')
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

                    $this->record->refresh();
                }),

            EditAction::make()
                ->tooltip('Editar datos de la ausencia'),

            DeleteAction::make()
                ->successRedirectUrl($this->getResource()::getUrl('index')),
        ];
    }
}
