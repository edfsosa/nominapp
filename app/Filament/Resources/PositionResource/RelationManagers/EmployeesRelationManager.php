<?php

namespace App\Filament\Resources\PositionResource\RelationManagers;

use App\Models\Employee;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class EmployeesRelationManager extends RelationManager
{
    protected static string $relationship = 'employees';
    protected static ?string $title = 'Empleados';
    protected static ?string $modelLabel = 'empleado';
    protected static ?string $pluralModelLabel = 'empleados';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('full_name')
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png')),

                TextColumn::make('full_name')
                    ->label('Nombre completo')
                    ->getStateUsing(fn(Employee $record) => $record->first_name . ' ' . $record->last_name)
                    ->description(fn(Employee $record) => 'CI: ' . $record->ci)
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->icon('heroicon-o-envelope')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->formatStateUsing(fn($state) => $state ? '+595 ' . $state : null)
                    ->icon('heroicon-o-phone')
                    ->url(fn(Employee $record) => $record->phone ? 'https://api.whatsapp.com/send?phone=595' . $record->phone : null)
                    ->openUrlInNewTab()
                    ->placeholder('Sin teléfono'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'suspended' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'suspended' => 'Suspendido',
                        default => $state,
                    })
                    ->sortable(),

                TextColumn::make('hire_date')
                    ->label('Fecha de Ingreso')
                    ->date('d/m/Y')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'suspended' => 'Suspendido',
                    ])
                    ->native(false)
                    ->multiple(),
            ])
            ->headerActions([
                ExportAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except(['photo', 'face_descriptor'])
                            ->withFilename('empleados_cargo_' . now()->format('d_m_Y_H_i_s'))
                            ->withWriterType(Excel::XLSX),
                    ])
                    ->label('Exportar a Excel')
                    ->color('info')
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except(['photo', 'face_descriptor'])
                            ->withFilename('empleados_cargo_' . now()->format('d_m_Y_H_i_s'))
                            ->withWriterType(Excel::XLSX),
                    ])
                    ->label('Exportar a Excel')
                    ->color('info')
                    ->icon('heroicon-o-arrow-down-tray'),
            ])
            ->emptyStateHeading('No hay empleados asignados')
            ->emptyStateDescription('Los empleados con este cargo aparecerán aquí.')
            ->emptyStateIcon('heroicon-o-user-group');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Información Personal')
                    ->schema([
                        Group::make([
                            TextEntry::make('ci')
                                ->label('Cédula de Identidad')
                                ->copyable()
                                ->icon('heroicon-o-identification'),

                            TextEntry::make('full_name')
                                ->label('Nombre Completo'),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('email')
                                ->label('Email')
                                ->icon('heroicon-o-envelope')
                                ->copyable(),

                            TextEntry::make('phone')
                                ->label('Teléfono')
                                ->icon('heroicon-o-phone'),
                        ])->columns(2),
                    ]),

                Section::make('Información Laboral')
                    ->schema([
                        Group::make([
                            TextEntry::make('hire_date')
                                ->label('Fecha de Ingreso')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-calendar'),

                            TextEntry::make('status')
                                ->label('Estado')
                                ->badge()
                                ->color(fn($state) => match ($state) {
                                    'active' => 'success',
                                    'inactive' => 'danger',
                                    'suspended' => 'warning',
                                    default => 'gray',
                                })
                                ->formatStateUsing(fn($state) => match ($state) {
                                    'active' => 'Activo',
                                    'inactive' => 'Inactivo',
                                    'suspended' => 'Suspendido',
                                    default => $state,
                                }),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('position.department.name')
                                ->label('Departamento')
                                ->icon('heroicon-o-building-office-2')
                                ->badge()
                                ->color('info'),

                            TextEntry::make('branch.name')
                                ->label('Sucursal')
                                ->icon('heroicon-o-map-pin')
                                ->badge()
                                ->color('primary'),
                        ])->columns(2),
                    ]),
            ]);
    }
}
