<?php

namespace App\Filament\Resources\LoanResource\Pages;

use App\Filament\Resources\LoanResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLoan extends EditRecord
{
    protected static string $resource = LoanResource::class;
    protected static ?string $title = 'Editar';

    /**
     * Define las acciones del encabezado de la página de edición.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            DeleteAction::make()
                ->icon('heroicon-o-trash')
                ->visible(fn() => $this->record->isPending() || $this->record->isCancelled()),
        ];
    }

    /**
     * Redirige a la vista de detalle tras guardar.
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * Título de la notificación al editar el préstamo.
     *
     * @return string|null
     */
    protected function getSavedNotification(): ?\Filament\Notifications\Notification
    {
        return \Filament\Notifications\Notification::make()
            ->success()
            ->title(ucfirst($this->record->type_label) . ' actualizado');
    }
}
