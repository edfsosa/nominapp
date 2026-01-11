<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Log;
use App\Services\AttendanceCalculator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Resources\AttendanceDayResource;

class ViewAttendanceDay extends ViewRecord
{
    protected static string $resource = AttendanceDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),

            Action::make('export')
                ->label('Exportar PDF')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn() => route('attendance-days.export', ['attendance_day' => $this->record->id]))
                ->openUrlInNewTab(),

            Action::make('calculate')
                ->label(fn() => $this->record->is_calculated ? 'Recalcular' : 'Calcular')
                ->icon('heroicon-o-calculator')
                ->color(fn() => $this->record->is_calculated ? 'warning' : 'success')
                ->tooltip(
                    fn() => $this->record->is_calculated
                        ? 'Última vez calculado: ' . $this->record->calculated_at?->diffForHumans()
                        : 'Este registro aún no ha sido calculado'
                )
                ->requiresConfirmation()
                ->modalHeading(
                    fn() => $this->record->is_calculated
                        ? 'Recalcular asistencia'
                        : 'Calcular asistencia'
                )
                ->modalDescription(
                    fn() => $this->record->is_calculated
                        ? 'Este registro ya fue calculado el ' . $this->record->calculated_at?->format('d/m/Y H:i') . '. ¿Deseas recalcularlo?'
                        : 'Se calcularán todos los campos de asistencia para este registro.'
                )
                ->modalSubmitActionLabel(fn() => $this->record->is_calculated ? 'Sí, recalcular' : 'Sí, calcular')
                ->action(function () {
                    try {
                        $wasCalculated = $this->record->is_calculated;

                        AttendanceCalculator::apply($this->record);
                        $this->record->save();
                        $this->record->refresh();

                        $action = $wasCalculated ? 'recalculado' : 'calculado';

                        Notification::make()
                            ->title("¡Registro {$action} exitosamente!")
                            ->body($this->record->getStatusMessage($wasCalculated))
                            ->success()
                            ->duration(5000)
                            ->send();
                    } catch (\Exception $e) {
                        Log::error("Error calculando AttendanceDay {$this->record->id}: {$e->getMessage()}", [
                            'trace' => $e->getTraceAsString()
                        ]);

                        Notification::make()
                            ->title('Error al calcular')
                            ->body('Ocurrió un error al procesar el registro. Revisa los logs para más detalles.')
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
