<?php

namespace App\Filament\Resources\AttendanceDayResource\Pages;

use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\AttendanceDayResource;
use App\Models\AttendanceDay;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceDays extends ListRecords
{
    protected static string $resource = AttendanceDayResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label('Todos')
                ->badge(AttendanceDay::count()),
            'present' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'present'))
                ->label('Presentes')
                ->badge(AttendanceDay::query()->where('status', 'present')->count())
                ->badgeColor('success'),
            'absent' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'absent'))
                ->label('Ausentes')
                ->badge(AttendanceDay::query()->where('status', 'absent')->count())
                ->badgeColor('danger'),
            'on_leave' => Tab::make()
                ->modifyQueryUsing(fn(Builder $query) => $query->where('status', 'on_leave'))
                ->label('De permiso')
                ->badge(AttendanceDay::query()->where('status', 'on_leave')->count())
                ->badgeColor('warning'),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'all';
    }
}
