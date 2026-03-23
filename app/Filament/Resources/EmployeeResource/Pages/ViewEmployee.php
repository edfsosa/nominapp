<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\FaceEnrollment;
use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    /**
     * Define las acciones disponibles en la vista de detalle del empleado.
     *
     * @return array
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('capture_face')
                ->label(fn() => $this->record->has_face ? 'Re-enrolar rostro' : 'Enrolar rostro')
                ->icon('heroicon-o-camera')
                ->color(fn() => $this->record->has_face ? 'warning' : 'success')
                ->url(fn() => route('face.capture', $this->record))
                ->visible(fn() => $this->record->status === 'active'),

            Action::make('generate_enrollment')
                ->label('Generar enlace de enrolamiento')
                ->icon('heroicon-o-link')
                ->color('info')
                ->visible(fn() => $this->record->status === 'active')
                ->requiresConfirmation()
                ->modalHeading('Generar Enlace de Registro Facial')
                ->modalDescription(function () {
                    $hours = app(GeneralSettings::class)->face_enrollment_expiry_hours;
                    return "Se generará un enlace temporal para que {$this->record->first_name} {$this->record->last_name} registre su rostro. El enlace expirará en {$hours} horas.";
                })
                ->modalSubmitActionLabel('Generar Enlace')
                ->action(function () {
                    $settings   = app(GeneralSettings::class);
                    $enrollment = FaceEnrollment::createForEmployee(
                        $this->record,
                        Auth::id(),
                        $settings->face_enrollment_expiry_hours
                    );

                    $url = route('face-enrollment.show', $enrollment->token);

                    Notification::make()
                        ->success()
                        ->title('Enlace generado — expira en ' . $settings->face_enrollment_expiry_hours . 'h')
                        ->body($url)
                        ->persistent()
                        ->actions([
                            NotificationAction::make('send_whatsapp')
                                ->label('Enviar por WhatsApp')
                                ->url('https://api.whatsapp.com/send?phone=595' . ltrim($this->record->phone ?? '', '0') . '&text=' . urlencode("Hola {$this->record->first_name}, usa este enlace para registrar tu rostro: {$url}"))
                                ->openUrlInNewTab()
                                ->visible(fn() => filled($this->record->phone)),
                        ])
                        ->send();
                }),

            Action::make('download_legajo')
                ->label('Descargar Legajo')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn() => route('employees.legajo', $this->record))
                ->openUrlInNewTab(),

            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
        ];
    }
}
