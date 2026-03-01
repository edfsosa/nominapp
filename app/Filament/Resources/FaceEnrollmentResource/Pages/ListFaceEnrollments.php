<?php

namespace App\Filament\Resources\FaceEnrollmentResource\Pages;

use App\Models\Employee;
use App\Models\FaceEnrollment;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Filament\Resources\FaceEnrollmentResource;

class ListFaceEnrollments extends ListRecords
{
    protected static string $resource = FaceEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FaceEnrollmentResource::getExcelExportAction(),

            Action::make('generate_link')
                ->label('Generar Enlace')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->tooltip('Generar un enlace de captura facial para un empleado')
                ->form([
                    Select::make('employee_id')
                        ->label('Empleado')
                        ->options(fn() => Employee::where('status', 'active')
                            ->orderBy('first_name')
                            ->get()
                            ->mapWithKeys(fn($e) => [$e->id => "{$e->full_name} (CI: {$e->ci})"]))
                        ->searchable()
                        ->native(false)
                        ->required()
                        ->helperText('Solo empleados activos'),

                    Select::make('expiry_hours')
                        ->label('Vigencia del enlace')
                        ->options([4 => '4 horas', 24 => '24 horas', 72 => '72 horas'])
                        ->default(24)
                        ->native(false)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $employee = Employee::findOrFail($data['employee_id']);

                    $existingActive = FaceEnrollment::where('employee_id', $employee->id)
                        ->where('status', 'pending_capture')
                        ->where('expires_at', '>', now())
                        ->exists();

                    if ($existingActive) {
                        Notification::make()
                            ->warning()
                            ->title('Ya existe un enlace activo')
                            ->body("Ya existe un enlace de captura vigente para {$employee->full_name}. Use la acción 'Ver enlace' en la tabla para acceder a él.")
                            ->persistent()
                            ->send();
                        return;
                    }

                    $enrollment = FaceEnrollment::createForEmployee(
                        $employee,
                        Auth::id(),
                        (int) $data['expiry_hours']
                    );

                    $url = route('face-enrollment.show', $enrollment->token);

                    Notification::make()
                        ->success()
                        ->title("Enlace generado para {$employee->full_name}")
                        ->body($url)
                        ->persistent()
                        ->actions([
                            \Filament\Notifications\Actions\Action::make('open')
                                ->label('Abrir enlace')
                                ->url($url)
                                ->openUrlInNewTab(),
                        ])
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        $statusOptions = FaceEnrollment::getStatusOptions();

        $expiredCount = FaceEnrollment::where('status', 'expired')
            ->orWhere(fn($q) => $q->where('status', 'pending_capture')->where('expires_at', '<', now()))
            ->count();

        return [
            'all' => Tab::make('Todos')
                ->badge(FaceEnrollment::count())
                ->badgeColor('gray')
                ->icon('heroicon-o-queue-list'),

            'pending_approval' => Tab::make($statusOptions['pending_approval'])
                ->modifyQueryUsing(fn(Builder $query) => $query->pendingApproval())
                ->badge(FaceEnrollment::where('status', 'pending_approval')->count())
                ->badgeColor('warning')
                ->icon('heroicon-o-clock'),

            'pending_capture' => Tab::make($statusOptions['pending_capture'])
                ->modifyQueryUsing(fn(Builder $query) => $query->pendingCapture()->where('expires_at', '>', now()))
                ->badge(FaceEnrollment::where('status', 'pending_capture')->where('expires_at', '>', now())->count())
                ->badgeColor('gray')
                ->icon('heroicon-o-camera'),

            'approved' => Tab::make($statusOptions['approved'])
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'approved'))
                ->badge(FaceEnrollment::where('status', 'approved')->count())
                ->badgeColor('success')
                ->icon('heroicon-o-check-circle'),

            'rejected' => Tab::make($statusOptions['rejected'])
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'rejected'))
                ->badge(FaceEnrollment::where('status', 'rejected')->count())
                ->badgeColor('danger')
                ->icon('heroicon-o-x-circle'),

            'expired' => Tab::make('Expirados')
                ->modifyQueryUsing(fn(Builder $query) => $query
                    ->where('status', 'expired')
                    ->orWhere(fn($q) => $q->where('status', 'pending_capture')->where('expires_at', '<', now())))
                ->badge($expiredCount ?: null)
                ->badgeColor('gray')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending_approval';
    }
}
