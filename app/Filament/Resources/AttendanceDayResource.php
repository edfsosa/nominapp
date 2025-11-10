<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceDayResource\Pages;
use App\Filament\Resources\AttendanceDayResource\RelationManagers;
use App\Models\AttendanceDay;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class AttendanceDayResource extends Resource
{
    protected static ?string $model = AttendanceDay::class;
    protected static ?string $navigationLabel = 'Asistencias';
    protected static ?string $label = 'asistencia';
    protected static ?string $pluralLabel = 'asistencias';
    protected static ?string $slug = 'asistencias';
    protected static ?string $navigationIcon = 'heroicon-o-check-circle';
    protected static ?string $navigationGroup = 'Asistencias';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('employee_id')
                    ->label('Empleado')
                    ->relationship(
                        name: 'employee',
                        modifyQueryUsing: fn(Builder $query) => $query->orderBy('first_name')->orderBy('last_name'),
                    )
                    ->getOptionLabelFromRecordUsing(fn(Model $record) => "{$record->first_name} {$record->last_name}")
                    ->searchable(['first_name', 'last_name'])
                    ->native(false)
                    ->required()
                    ->preload()
                    ->disabled(),
                DatePicker::make('date')
                    ->label('Fecha')
                    ->required()
                    ->disabled(),
                Select::make('status')
                    ->label('Estado')
                    ->options([
                        'present' => 'Presente',
                        'absent' => 'Ausente',
                        'on_leave' => 'De permiso',
                    ])
                    ->native(false)
                    ->required(),
                TextInput::make('total_hours')
                    ->label('Horas trabajadas')
                    ->readOnly(),
                TextInput::make('net_hours')
                    ->label('Horas netas')
                    ->readOnly(),
                TextInput::make('expected_hours')
                    ->label('Horas esperadas')
                    ->readOnly(),
                TextInput::make('late_minutes')
                    ->label('Minutos tarde')
                    ->readOnly(),
                TextInput::make('early_leave_minutes')
                    ->label('Salida anticipada')
                    ->readOnly(),
                TextInput::make('extra_hours')
                    ->label('Horas extra')
                    ->readOnly(),
                TextInput::make('break_minutes')
                    ->label('Descansos')
                    ->readOnly(),

                TextInput::make('check_in_time')
                    ->label('Entrada')
                    ->readOnly(),
                TextInput::make('check_out_time')
                    ->label('Salida')
                    ->readOnly(),
                Textarea::make('notes')
                    ->label('Notas')
                    ->maxLength(255)
                    ->rows(1),

                TextInput::make('expected_check_in')
                    ->label('Entrada esperada')
                    ->readOnly(),
                TextInput::make('expected_check_out')
                    ->label('Salida esperada')
                    ->readOnly(),
                TextInput::make('expected_break_minutes')
                    ->label('Descanso esperado')
                    ->readOnly(),

                Toggle::make('anomaly_flag')
                    ->label('Anomalía detectada'),
                Toggle::make('manual_adjustment')
                    ->label('Ajustado manualmente'),
                Toggle::make('overtime_approved')
                    ->label('Horas extra aprobadas'),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->numeric()
                    ->copyable()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('employee.first_name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.last_name')
                    ->label('Apellido')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.position.name')
                    ->label('Cargo')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employee.position.department.name')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'present' => 'Presente',
                        'absent' => 'Ausente',
                        'on_leave' => 'De permiso',
                        default => 'Desconocido',
                    })
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'present' => 'success',
                        'absent' => 'danger',
                        'on_leave' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
            ])->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('employee.ci')
                    ->label('CI')
                    ->placeholder('Seleccionar CI')
                    ->relationship('employee', 'ci')
                    ->native(false)
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->placeholder('Seleccionar estado')
                    ->options([
                        'present' => 'Presente',
                        'absent' => 'Ausente',
                        'on_leave' => 'De permiso',
                    ])
                    ->native(false),
                SelectFilter::make('employee.branch_id')
                    ->label('Sucursal')
                    ->placeholder('Seleccionar sucursal')
                    ->relationship('employee.branch', 'name')
                    ->native(false),
                SelectFilter::make('employee.position.department_id')
                    ->label('Departamento')
                    ->placeholder('Seleccionar departamento')
                    ->relationship('employee.position.department', 'name')
                    ->native(false),
                Filter::make('date')
                    ->label('Fecha')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde')
                            ->native(false),
                        DatePicker::make('to')
                            ->label('Hasta')
                            ->after('from')
                            ->native(false),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date))
                            ->when($data['to'], fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date));
                    }),
            ])
            ->actions([])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except([
                                'created_at',
                                'updated_at',
                            ])
                            ->withFilename('asistencias_' . now()->format('d_m_Y_H_i_s')),
                    ])
                    ->label('Exportar')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-tray')
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\EventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceDays::route('/'),
            'create' => Pages\CreateAttendanceDay::route('/create'),
            'edit' => Pages\EditAttendanceDay::route('/{record}/edit'),
        ];
    }
}
