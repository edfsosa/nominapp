<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use App\Filament\Resources\AttendanceDayResource;
use App\Services\AttendanceCalculator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;

class ViewAttendanceDay extends ViewRecord
{
    protected static string $resource = AttendanceDayResource::class;

    protected function getHeaderActions(): array
    {
        return [

            // Acción para exportar el día de asistencia en PDF
            Action::make('export')
                ->label('Exportar (PDF)')
                ->icon('heroicon-o-printer')
                ->url(fn() => route('attendance-days.export', ['attendance_day' => $this->record->id]))
                ->openUrlInNewTab(),

            // Calcula usando el Service AttendanceCalculator para el día actual
            Action::make('calculate')
                ->label(fn() => $this->record->is_calculated ? 'Recalcular Asistencia' : 'Calcular Asistencia')
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
                        : 'Se calcularán todos los campos de asistencia (horas trabajadas, descansos, llegadas tarde, etc.) para este registro.'
                )
                ->modalSubmitActionLabel(fn() => $this->record->is_calculated ? 'Sí, recalcular' : 'Sí, calcular')
                ->action(function () {
                    try {
                        $wasCalculated = $this->record->is_calculated;

                        AttendanceCalculator::apply($this->record);
                        $this->record->save();

                        // Refrescar el registro para mostrar los cambios
                        $this->record->refresh();

                        $action = $wasCalculated ? 'recalculado' : 'calculado';

                        // Mensaje más detallado según el status
                        $statusMessages = [
                            'present' => "✓ Empleado presente - Cálculos {$action}s",
                            'absent' => "⚠ Empleado ausente",
                            'on_leave' => "📋 Empleado con permiso/vacaciones",
                            'holiday' => "🎉 Día feriado",
                            'weekend' => "📅 Fin de semana",
                        ];

                        $message = $statusMessages[$this->record->status] ?? "Cálculo {$action}";

                        Notification::make()
                            ->title("¡Registro {$action} exitosamente!")
                            ->body($message)
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
