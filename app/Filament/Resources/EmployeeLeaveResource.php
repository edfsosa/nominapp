<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeLeaveResource\Pages;
use App\Filament\Resources\EmployeeLeaveResource\RelationManagers;
use App\Models\EmployeeLeave;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class EmployeeLeaveResource extends Resource
{
    protected static ?string $model = EmployeeLeave::class;
    protected static ?string $navigationLabel = 'Permisos';
    protected static ?string $label = 'Permiso';
    protected static ?string $pluralLabel = 'Permisos';
    protected static ?string $slug = 'permisos';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->searchable()
                    ->required()
                    ->preload(),
                Forms\Components\Select::make('type')
                    ->label('Tipo de Permiso')
                    ->options([
                        'medical_leave' => 'Reposo Médico',
                        'vacation' => 'Vacaciones',
                        'day_off' => 'Día Libre',
                        'maternity_leave' => 'Permiso Maternidad',
                        'paternity_leave' => 'Permiso Paternidad',
                        'unpaid_leave' => 'Permiso Sin Goce de Sueldo',
                        'other' => 'Otro',
                    ])
                    ->native(false)
                    ->required(),
                Forms\Components\DatePicker::make('start_date')
                    ->label('Desde')
                    ->default(now())
                    ->native(false)
                    ->required(),
                Forms\Components\DatePicker::make('end_date')
                    ->label('Hasta')
                    ->default(now())
                    ->native(false)
                    ->required(),
                Forms\Components\Textarea::make('reason')
                    ->label('Descripción/Motivo')
                    ->maxLength(500)
                    ->nullable()
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('document_path')
                    ->label('Documento Comprobante')
                    ->disk('public')
                    ->directory('employee_leaves')
                    ->visibility('public')
                    ->nullable()
                    ->acceptedFileTypes(['application/pdf', 'image/*'])
                    ->maxSize(10240) // 10 MB
                    ->downloadable()
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->native(false)
                    ->default('pending')
                    ->columnSpanFull()
                    ->hiddenOn('create')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee.ci')
                    ->label('CI')
                    ->numeric()
                    ->sortable()
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('employee.first_name')
                    ->label('Nombre(s)')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.last_name')
                    ->label('Apellido(s)')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.position.name')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.position.department.name')
                    ->label('Departamento')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.branch.name')
                    ->label('Sucursal')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'medical_leave' => 'Reposo Médico',
                        'vacation' => 'Vacaciones',
                        'day_off' => 'Día Libre',
                        'maternity_leave' => 'Permiso Maternidad',
                        'paternity_leave' => 'Permiso Paternidad',
                        'unpaid_leave' => 'Permiso Sin Goce de Sueldo',
                        'other' => 'Otro',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Desde')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Hasta')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'approved' => 'Aprobado',
                        'rejected' => 'Rechazado',
                    ])
                    ->placeholder('Seleccionar estado')
                    ->native(false),
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options([
                        'medical_leave' => 'Reposo Médico',
                        'vacation' => 'Vacaciones',
                        'day_off' => 'Día Libre',
                        'maternity_leave' => 'Permiso Maternidad',
                        'paternity_leave' => 'Permiso Paternidad',
                        'unpaid_leave' => 'Permiso Sin Goce de Sueldo',
                        'other' => 'Otro',
                    ])
                    ->placeholder('Seleccionar tipo')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except([
                                'created_at',
                                'updated_at',
                                'document_path',
                            ])
                            ->withFilename('permisos_' . now()->format('d_m_Y_H_i_s')),
                    ])
                    ->label('Exportar')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-tray')
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEmployeeLeaves::route('/'),
        ];
    }
}
