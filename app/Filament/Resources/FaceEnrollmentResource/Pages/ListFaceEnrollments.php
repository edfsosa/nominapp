<?php

namespace App\Filament\Resources\FaceEnrollmentResource\Pages;

use App\Models\FaceEnrollment;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\FaceEnrollmentResource;

class ListFaceEnrollments extends ListRecords
{
    // Especifica el recurso asociado a esta página, que es el recurso de FaceEnrollment
    protected static string $resource = FaceEnrollmentResource::class;

    /**
     * Devuelve las pestañas para la página de listado, cada una con su propia consulta modificada para mostrar registros según su estado.
     *
     * @return array
     */
    public function getTabs(): array
    {
        $statusOptions = FaceEnrollment::getStatusOptions();

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
        ];
    }

    /**
     * Devuelve la pestaña que debe estar activa por defecto al cargar la página
     *
     * @return string|integer|null
     */
    public function getDefaultActiveTab(): string|int|null
    {
        return 'pending_approval';
    }
}
