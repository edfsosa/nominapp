<?php

namespace App\Filament\Resources;

use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers;
use App\Models\Employee;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationLabel = 'Empleados';
    protected static ?string $label = 'Empleado';
    protected static ?string $pluralLabel = 'Empleados';
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    // Formulario de creación y edición
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos Personales')
                    ->description('Ingrese los datos personales del empleado.')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                FileUpload::make('photo')
                                    ->label('Foto')
                                    ->disk('public')
                                    ->directory('employees')
                                    ->image()
                                    ->avatar()
                                    ->imageEditor()
                                    ->circleCropper()
                                    ->nullable()
                                    ->downloadable(),
                                TextInput::make('ci')
                                    ->label('Cédula de Identidad')
                                    ->integer()
                                    ->minValue(1)
                                    ->required()
                                    ->maxLength(20),
                                TextInput::make('first_name')
                                    ->label('Nombre(s)')
                                    ->required()
                                    ->maxLength(60),
                                TextInput::make('last_name')
                                    ->label('Apellido(s)')
                                    ->required()
                                    ->maxLength(60),
                            ]),
                        Grid::make(3)
                            ->schema([
                                DatePicker::make('birth_date')
                                    ->label('Fecha de Nacimiento')
                                    ->displayFormat('d/m/Y')
                                    ->minDate(now()->subYears(100))
                                    ->maxDate(now())
                                    ->default(now()->subYears(18))
                                    ->required(),
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->prefix('+595')
                                    ->minLength(7)
                                    ->maxLength(30),
                                TextInput::make('email')
                                    ->label('Correo Electrónico')
                                    ->email()
                                    ->maxLength(60)
                                    ->unique(Employee::class, 'email', ignoreRecord: true),
                            ]),
                    ]),

                Section::make('Detalles de Empleo')
                    ->description('Ingrese los detalles de empleo del empleado.')
                    ->schema([
                        Grid::make(5)
                            ->schema([
                                DatePicker::make('hire_date')
                                    ->label('Fecha de Contratación')
                                    ->displayFormat('d/m/Y')
                                    ->minDate(now()->subYears(30))
                                    ->maxDate(now()->addYears(1))
                                    ->default(now())
                                    ->required(),
                                Select::make('payroll_type')
                                    ->label('Tipo de Nómina')
                                    ->options([
                                        'monthly' => 'Mensual',
                                        'biweekly' => 'Quincenal',
                                        'weekly' => 'Semanal',
                                    ])
                                    ->native(false)
                                    ->required(),
                                Select::make('employment_type')
                                    ->label('Tipo de Empleo')
                                    ->options([
                                        'full_time' => 'Tiempo Completo',
                                        'day_laborer' => 'Jornalero',
                                    ])
                                    ->native(false)
                                    ->required(),
                                Select::make('payment_method')
                                    ->label('Método de Pago')
                                    ->options([
                                        'debit' => 'Tarjeta de Débito',
                                        'cash' => 'Efectivo',
                                        'check' => 'Cheque',
                                    ])
                                    ->native(false)
                                    ->required(),
                                TextInput::make('base_salary')
                                    ->label('Salario Base')
                                    ->integer()
                                    ->minValue(0)
                                    ->maxLength(10)
                                    ->step(1.00)
                                    ->nullable()
                                    ->visible(fn(Forms\Get $get) => $get('employment_type') === 'full_time')
                                    ->prefix('Gs.')
                                    ->default(0),
                            ]),
                        Grid::make(5)
                            ->schema([
                                TextInput::make('daily_rate')
                                    ->label('Tarifa Diaria')
                                    ->integer()
                                    ->minValue(0)
                                    ->maxLength(10)
                                    ->step(1.00)
                                    ->nullable()
                                    ->visible(fn(Forms\Get $get) => $get('employment_type') === 'day_laborer')
                                    ->prefix('Gs.')
                                    ->default(0),
                                Select::make('position_id')
                                    ->label('Cargo')
                                    ->options(function () {
                                        return \App\Models\Position::with('department')
                                            ->get()
                                            ->mapWithKeys(function ($position) {
                                                $label = $position->name;
                                                if ($position->department) {
                                                    $label .= ' (' . $position->department->name . ')';
                                                }
                                                return [$position->id => $label];
                                            })
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required(),
                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required(),
                                Select::make('schedule_id')
                                    ->label('Horario')
                                    ->relationship('schedule', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required(),
                                Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'active' => 'Activo',
                                        'inactive' => 'Inactivo',
                                        'suspended' => 'Suspendido',
                                    ])
                                    ->searchable()
                                    ->native(false)
                                    ->hiddenOn('create')
                                    ->default('activo')
                                    ->required(),
                                Textarea::make('face_descriptor')
                                    ->label('Descriptor Facial')
                                    ->rows(1)
                                    ->maxLength(500)
                                    ->hiddenOn('create')
                                    ->readonly(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular(),
                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('first_name')
                    ->label('Nombre(s)')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Apellido(s)')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->prefix('+595')
                    ->url(fn(Employee $record): ?string => $record->phone ? 'https://api.whatsapp.com/send?phone=595' . $record->phone : null)
                    ->openUrlInNewTab()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->url(fn(Employee $record): ?string => $record->email ? 'mailto:' . $record->email : null)
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hire_date')
                    ->label('Contratación')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payroll_type')
                    ->label('Tipo de Nómina')
                    ->badge()
                    ->colors([
                        'primary' => 'monthly',
                        'secondary' => 'biweekly',
                        'info' => 'weekly',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'monthly' => 'Mensual',
                        'biweekly' => 'Quincenal',
                        'weekly' => 'Semanal',
                        default => $state,
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employment_type')
                    ->label('Tipo de Empleo')
                    ->badge()
                    ->colors([
                        'success' => 'full_time',
                        'warning' => 'day_laborer',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'full_time' => 'Tiempo Completo',
                        'day_laborer' => 'Jornalero',
                        default => $state,
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('base_salary')
                    ->label('Salario base (₲)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('daily_rate')
                    ->label('Tarifa Diaria (₲)')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_method')
                    ->label('Método de Pago')
                    ->badge()
                    ->colors([
                        'primary' => 'debit',
                        'secondary' => 'cash',
                        'info' => 'check',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'debit' => 'Tarjeta de Débito',
                        'cash' => 'Efectivo',
                        'check' => 'Cheque',
                        default => $state,
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('position.name')
                    ->label('Cargo')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('position.department.name')
                    ->label('Departamento')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('schedule.name')
                    ->label('Horario')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                        'warning' => 'suspended',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'suspended' => 'Suspendido',
                        default => $state,
                    })
                    ->sortable()
                    ->searchable(),
                IconColumn::make('has_face')
                    ->label('Rostro')
                    ->boolean()
                    ->state(fn(Employee $r) => filled($r->face_descriptor))
                    ->trueIcon('heroicon-m-check-circle')
                    ->falseIcon('heroicon-m-x-circle')
                    ->color(fn(bool $state) => $state ? 'success' : 'warning'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'active' => 'Activo',
                        'inactive' => 'Inactivo',
                        'suspended' => 'Suspendido',
                    ])
                    ->placeholder('Seleccionar estado')
                    ->native(false),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->placeholder('Seleccionar sucursal')
                    ->native(false),
                SelectFilter::make('payroll_type')
                    ->label('Tipo de Nómina')
                    ->options([
                        'monthly' => 'Mensual',
                        'biweekly' => 'Quincenal',
                        'weekly' => 'Semanal',
                    ])
                    ->placeholder('Seleccionar tipo de nómina')
                    ->native(false),
                SelectFilter::make('payment_method')
                    ->label('Método de Pago')
                    ->options([
                        'debit' => 'Tarjeta de Débito',
                        'cash' => 'Efectivo',
                        'check' => 'Cheque',
                    ])
                    ->placeholder('Seleccionar método de pago')
                    ->native(false),
                Filter::make('created_at')
                    ->label('Fecha de Creación')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('Desde')
                            ->native(false)
                            ->closeOnDateSelection(),
                        DatePicker::make('created_until')
                            ->label('Hasta')
                            ->native(false)
                            ->closeOnDateSelection(),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('capture_face')
                    ->label(fn(Employee $record): string => $record->face_descriptor ? 'Actualizar Rostro' : 'Capturar Rostro')
                    ->icon('heroicon-o-camera')
                    ->url(fn(Employee $record): string => route('face.capture', $record))
                    ->color('success')
                    ->tooltip('Ir a captura facial')
                    ->visible(fn(Employee $record): string => $record->status === 'active'),
            ])
            ->bulkActions([
                ExportBulkAction::make()
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except([
                                'photo',
                                'face_descriptor',
                                'created_at',
                                'updated_at',
                            ])
                            ->withFilename('empleados_' . now()->format('d_m_Y_H_i_s')),
                    ])
                    ->label('Exportar')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-down-tray')
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\DocumentsRelationManager::class,
            RelationManagers\VacationsRelationManager::class,
            RelationManagers\LeavesRelationManager::class,
            RelationManagers\EmployeeDeductionsRelationManager::class,
            RelationManagers\EmployeePerceptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
