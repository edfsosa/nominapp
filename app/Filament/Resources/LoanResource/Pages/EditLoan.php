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
     */
    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->label('Ver')->icon('heroicon-o-eye')->color('gray'),
            DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->modalHeading('¿Eliminar préstamo?')
                ->modalSubmitActionLabel('Sí, eliminar')
                ->visible(fn () => $this->record->isPending() || $this->record->isCancelled()),
        ];
    }

    /**
     * Redirige a la vista de detalle tras guardar.
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
            ->title('Préstamo actualizado');
    }
}
