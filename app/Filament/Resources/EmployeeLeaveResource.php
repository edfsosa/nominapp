<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeLeaveResource\Pages;
use App\Models\EmployeeLeave;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class EmployeeLeaveResource extends Resource
{
    protected static ?string $model = EmployeeLeave::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Permisos';
    protected static ?string $modelLabel = 'permiso';
    protected static ?string $pluralModelLabel = 'permisos';
    protected static ?string $navigationGroup = 'Empleados';
    protected static ?string $slug = 'permisos';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Permiso')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship('employee', 'first_name')
                            ->getOptionLabelFromRecordUsing(fn($record) => "{$record->first_name} {$record->last_name}")
                            ->searchable(['first_name', 'last_name', 'email'])
                            ->preload()
                            ->required()
                            ->columnSpan(2),

                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending' => 'Pendiente',
                                'approved' => 'Aprobado',
                                'rejected' => 'Rechazado',
                            ])
                            ->default('pending')
                            ->native(false)
                            ->required()
                            ->disabled(fn(?EmployeeLeave $record) => $record === null)
                            ->dehydrated()
                            ->columnSpan(1),

                        Select::make('type')
                            ->label('Tipo de permiso')
                            ->options([
                                'medical_leave' => 'Reposo médico',
                                'vacation' => 'Vacaciones',
                                'day_off' => 'Día libre',
                                'maternity_leave' => 'Permiso de maternidad',
                                'paternity_leave' => 'Permiso de paternidad',
                                'unpaid_leave' => 'Permiso sin goce de sueldo',
                                'other' => 'Otro',
                            ])
                            ->native(false)
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Section::make('Período del Permiso')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Fecha de inicio')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, $set) {
                                $startDate = $get('start_date');
                                $endDate = $get('end_date');

                                if ($startDate && $endDate && $endDate < $startDate) {
                                    $set('end_date', null);
                                }
                            })
                            ->columnSpan(1),

                        DatePicker::make('end_date')
                            ->label('Fecha de fin')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required()
                            ->minDate(fn(Get $get) => $get('start_date'))
                            ->columnSpan(1),

                        Placeholder::make('duration')
                            ->label('Duración')
                            ->content(function (Get $get) {
                                $start = $get('start_date');
                                $end = $get('end_date');

                                if (!$start || !$end) {
                                    return '-';
                                }

                                $days = \Carbon\Carbon::parse($start)
                                    ->diffInDays(\Carbon\Carbon::parse($end)) + 1;

                                return $days . ' ' . ($days === 1 ? 'día' : 'días');
                            })
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Section::make('Detalles')
                    ->schema([
                        FileUpload::make('document_path')
                            ->label('Documento de soporte')
                            ->helperText(fn(Get $get) => $get('type') === 'medical_leave'
                                ? 'Comprobante médico requerido'
                                : 'Sube un documento relevante (opcional)')
                            ->disk('public')
                            ->directory('employee-leaves')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(5120)
                            ->downloadable()
                            ->openable()
                            ->required(fn(Get $get) => $get('type') === 'medical_leave')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('employee.first_name')
                    ->label('Empleado')
                    ->formatStateUsing(fn($record) => "{$record->employee->first_name} {$record->employee->last_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'medical_leave' => 'Reposo médico',
                        'vacation' => 'Vacaciones',
                        'day_off' => 'Día libre',
                        'maternity_leave' => 'Maternidad',
                        'paternity_leave' => 'Paternidad',
                        'unpaid_leave' => 'Sin goce de sueldo',
                        'other' => 'Otro',
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'medical_leave' => 'danger',
                        'vacation' => 'success',
                        'day_off' => 'info',
                        'maternity_leave' => 'pink',
                        'paternity_leave' => 'blue',
                        'unpaid_leave' => 'warning',
                        'other' => 'gray',
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('days')
                    ->label('Días')
                    ->getStateUsing(function ($record) {
                        $start = \Carbon\Carbon::parse($record->start_date);
                        $end = \Carbon\Carbon::parse($record->end_date);
                        return $start->diffInDays($end) + 1;
                    })
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    })
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('document_path')
                    ->label('Documento')
                    ->formatStateUsing(fn($state) => $state ? 'Sí' : 'No')
                    ->badge()
                    ->color(fn($state) => $state ? 'success' : 'gray')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Solicitado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('start_date', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo de permiso')
                    ->options([
                        'medical_leave' => 'Reposo médico',
                        'vacation' => 'Vacaciones',
                        'day_off' => 'Día libre',
                        'maternity_leave' => 'Maternidad',
                        'paternity_leave' => 'Paternidad',
                        'unpaid_leave' => 'Sin goce de sueldo',
                        'other' => 'Otro',
                    ])
                    ->multiple(),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->multiple(),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->first_name} {$record->last_name}")
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->except([
                                    'created_at',
                                    'updated_at',
                                ])
                                ->withFilename('employee_leaves_export.xlsx'),
                        ])
                        ->label('Exportar seleccionados')
                        ->color('success')
                        ->icon('heroicon-o-arrow-down-tray'),
                ]),
            ])
            ->emptyStateHeading('No hay permisos registrados')
            ->emptyStateDescription('Comienza registrando un nuevo permiso o ausencia.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Información del Empleado')
                    ->schema([
                        TextEntry::make('employee.first_name')
                            ->label('Nombre completo')
                            ->formatStateUsing(fn($record) => "{$record->employee->first_name} {$record->employee->last_name}")
                            ->icon('heroicon-o-user')
                            ->columnSpan(1),

                        TextEntry::make('employee.email')
                            ->label('Email')
                            ->icon('heroicon-o-envelope')
                            ->copyable()
                            ->columnSpan(1),

                        TextEntry::make('employee.position.department.name')
                            ->label('Departamento')
                            ->icon('heroicon-o-building-office')
                            ->badge()
                            ->color('info')
                            ->columnSpan(1),

                        TextEntry::make('employee.position.name')
                            ->label('Puesto')
                            ->icon('heroicon-o-briefcase')
                            ->badge()
                            ->color('primary')
                            ->columnSpan(1),
                    ])
                    ->columns(4),

                InfolistSection::make('Detalles del Permiso')
                    ->schema([
                        TextEntry::make('type')
                            ->label('Tipo de permiso')
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'medical_leave' => 'Reposo médico',
                                'vacation' => 'Vacaciones',
                                'day_off' => 'Día libre',
                                'maternity_leave' => 'Permiso de maternidad',
                                'paternity_leave' => 'Permiso de paternidad',
                                'unpaid_leave' => 'Permiso sin goce de sueldo',
                                'other' => 'Otro',
                            })
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'medical_leave' => 'danger',
                                'vacation' => 'success',
                                'day_off' => 'info',
                                'maternity_leave' => 'pink',
                                'paternity_leave' => 'blue',
                                'unpaid_leave' => 'warning',
                                'other' => 'gray',
                            })
                            ->columnSpan(1),

                        TextEntry::make('status')
                            ->label('Estado')
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'pending' => 'Pendiente',
                                'approved' => 'Aprobado',
                                'rejected' => 'Rechazado',
                            })
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'pending' => 'warning',
                                'approved' => 'success',
                                'rejected' => 'danger',
                            })
                            ->icon(fn(string $state): string => match ($state) {
                                'pending' => 'heroicon-o-clock',
                                'approved' => 'heroicon-o-check-circle',
                                'rejected' => 'heroicon-o-x-circle',
                            })
                            ->columnSpan(1),

                        TextEntry::make('document_path')
                            ->label('Documento adjunto')
                            ->formatStateUsing(fn($state) => $state ? 'Ver documento' : 'Sin documento adjunto')
                            ->url(fn($record) => $record->document_path
                                ? asset('storage/' . $record->document_path)
                                : null)
                            ->openUrlInNewTab()
                            ->icon(fn($state) => $state ? 'heroicon-o-document-text' : 'heroicon-o-x-circle')
                            ->color(fn($state) => $state ? 'success' : 'gray'),
                    ])
                    ->columns(3),

                InfolistSection::make('Período del Permiso')
                    ->schema([
                        TextEntry::make('start_date')
                            ->label('Fecha de inicio')
                            ->date('d/m/Y')
                            ->icon('heroicon-o-calendar')
                            ->columnSpan(1),

                        TextEntry::make('end_date')
                            ->label('Fecha de fin')
                            ->date('d/m/Y')
                            ->icon('heroicon-o-calendar')
                            ->columnSpan(1),

                        TextEntry::make('duration')
                            ->label('Duración total')
                            ->getStateUsing(function ($record) {
                                $start = \Carbon\Carbon::parse($record->start_date);
                                $end = \Carbon\Carbon::parse($record->end_date);
                                $days = $start->diffInDays($end) + 1;
                                return $days . ' ' . ($days === 1 ? 'día' : 'días');
                            })
                            ->badge()
                            ->color('primary')
                            ->icon('heroicon-o-clock')
                            ->columnSpan(1),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployeeLeaves::route('/'),
            'create' => Pages\CreateEmployeeLeaves::route('/create'),
            'view' => Pages\ViewEmployeeLeaves::route('/{record}'),
            'edit' => Pages\EditEmployeeLeaves::route('/{record}/edit'),
        ];
    }
}
