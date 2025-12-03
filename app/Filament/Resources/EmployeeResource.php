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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationLabel = 'Empleados';
    protected static ?string $label = 'empleado';
    protected static ?string $pluralLabel = 'empleados';
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Empleados';

    // Formulario de creación y edición
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Datos Personales')
                    ->description('Información personal del empleado')
                    ->icon('heroicon-o-user')
                    ->collapsible()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                FileUpload::make('photo')
                                    ->label('Fotografía')
                                    ->disk('public')
                                    ->directory('employees/photos')
                                    ->image()
                                    ->avatar()
                                    ->imageEditor()
                                    ->circleCropper()
                                    ->maxSize(2048) // 2 MB
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/jpg'])
                                    ->downloadable()
                                    ->previewable()
                                    ->helperText('Sube una foto del empleado (máx. 2 MB)')
                                    ->nullable()
                                    ->columnSpan(1),

                                Grid::make(1)
                                    ->schema([
                                        TextInput::make('ci')
                                            ->label('Cédula de Identidad')
                                            ->placeholder('Ej: 1234567')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(99999999)
                                            ->required()
                                            ->unique(Employee::class, 'ci', ignoreRecord: true)
                                            ->maxLength(20)
                                            ->helperText('Sin puntos ni guiones'),

                                        TextInput::make('first_name')
                                            ->label('Nombre(s)')
                                            ->placeholder('Ej: Juan Carlos')
                                            ->required()
                                            ->maxLength(60)
                                            ->string()
                                            ->autocapitalize('words'),

                                        TextInput::make('last_name')
                                            ->label('Apellido(s)')
                                            ->placeholder('Ej: González López')
                                            ->required()
                                            ->maxLength(60)
                                            ->string()
                                            ->autocapitalize('words'),
                                    ])
                                    ->columnSpan(3),
                            ]),

                        Grid::make(3)
                            ->schema([
                                DatePicker::make('birth_date')
                                    ->label('Fecha de nacimiento')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->minDate(now()->subYears(100))
                                    ->maxDate(now()->subYears(18))
                                    ->default(now()->subYears(25))
                                    ->helperText('Debe ser mayor de 18 años')
                                    ->nullable(),

                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->prefix('+595')
                                    ->placeholder('971123456')
                                    ->minLength(7)
                                    ->maxLength(30)
                                    ->helperText('Formato: 971123456')
                                    ->nullable(),

                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->placeholder('empleado@empresa.com')
                                    ->maxLength(100)
                                    ->unique(Employee::class, 'email', ignoreRecord: true)
                                    ->nullable(),
                            ]),
                    ]),

                Section::make('Información Laboral')
                    ->description('Datos del empleo y contratación')
                    ->icon('heroicon-o-briefcase')
                    ->collapsible()
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                DatePicker::make('hire_date')
                                    ->label('Fecha de contratación')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->minDate(now()->subYears(30))
                                    ->maxDate(now()->addYears(1))
                                    ->default(now())
                                    ->required(),

                                Select::make('employment_type')
                                    ->label('Tipo de empleo')
                                    ->options([
                                        'full_time' => 'Tiempo Completo',
                                        'day_laborer' => 'Jornalero',
                                    ])
                                    ->native(false)
                                    ->live()
                                    ->default('full_time')
                                    ->required()
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        // Limpiar campos según el tipo
                                        if ($state === 'full_time') {
                                            $set('daily_rate', null);
                                        } else {
                                            $set('base_salary', null);
                                        }
                                    }),

                                Select::make('payroll_type')
                                    ->label('Tipo de nómina')
                                    ->options([
                                        'monthly' => 'Mensual',
                                        'biweekly' => 'Quincenal',
                                        'weekly' => 'Semanal',
                                    ])
                                    ->native(false)
                                    ->default('monthly')
                                    ->required(),

                                Select::make('payment_method')
                                    ->label('Método de pago')
                                    ->options([
                                        'debit' => 'Tarjeta de Débito',
                                        'cash' => 'Efectivo',
                                        'check' => 'Cheque',
                                    ])
                                    ->native(false)
                                    ->default('debit')
                                    ->required(),
                            ]),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('base_salary')
                                    ->label('Salario base mensual')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(999999999999)
                                    ->step(1000)
                                    ->prefix('₲')
                                    ->placeholder('0')
                                    ->helperText('Salario base en guaraníes')
                                    ->required(fn(Forms\Get $get) => $get('employment_type') === 'full_time')
                                    ->visible(fn(Forms\Get $get) => $get('employment_type') === 'full_time')
                                    ->dehydrated(fn(Forms\Get $get) => $get('employment_type') === 'full_time'),

                                TextInput::make('daily_rate')
                                    ->label('Tarifa diaria')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(999999999999)
                                    ->step(1000)
                                    ->prefix('₲')
                                    ->placeholder('0')
                                    ->helperText('Tarifa por día trabajado')
                                    ->required(fn(Forms\Get $get) => $get('employment_type') === 'day_laborer')
                                    ->visible(fn(Forms\Get $get) => $get('employment_type') === 'day_laborer')
                                    ->dehydrated(fn(Forms\Get $get) => $get('employment_type') === 'day_laborer'),
                            ]),
                    ]),

                Section::make('Asignaciones')
                    ->description('Cargo, sucursal y horario del empleado')
                    ->icon('heroicon-o-building-office')
                    ->collapsible()
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Select::make('position_id')
                                    ->label('Cargo')
                                    ->options(function () {
                                        return \App\Models\Position::with('department')
                                            ->get()
                                            ->mapWithKeys(function ($position) {
                                                $label = $position->name;
                                                if ($position->department) {
                                                    $label .= ' - ' . $position->department->name;
                                                }
                                                return [$position->id => $label];
                                            })
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->helperText('Selecciona el cargo del empleado'),

                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->relationship('branch', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->helperText('Sucursal donde trabajará'),

                                Select::make('schedule_id')
                                    ->label('Horario')
                                    ->relationship('schedule', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->helperText('Horario de trabajo asignado'),
                            ]),
                    ]),

                Section::make('Estado y Reconocimiento Facial')
                    ->description('Estado del empleado y datos biométricos')
                    ->icon('heroicon-o-finger-print')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('status')
                                    ->label('Estado del empleado')
                                    ->options([
                                        'active' => 'Activo',
                                        'inactive' => 'Inactivo',
                                        'suspended' => 'Suspendido',
                                    ])
                                    ->native(false)
                                    ->default('active')
                                    ->required()
                                    ->hiddenOn('create')
                                    ->helperText('Estado actual del empleado en la empresa'),

                                Placeholder::make('status_info')
                                    ->label('Estado')
                                    ->content('El empleado se creará con estado "Activo" por defecto')
                                    ->visibleOn('create'),

                                Textarea::make('face_descriptor')
                                    ->label('Descriptor facial')
                                    ->rows(3)
                                    ->maxLength(5000)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Datos biométricos del rostro (generado automáticamente por el sistema)')
                                    ->hiddenOn('create')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ])
            ->columns(1);
    }

    // Tabla de listado
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(url('/images/default-avatar.png'))
                    ->size(40),

                TextColumn::make('full_name')
                    ->label('Nombre completo')
                    ->getStateUsing(fn(Employee $record) => $record->first_name . ' ' . $record->last_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->description(fn(Employee $record) => 'CI: ' . $record->ci)
                    ->weight('medium')
                    ->wrap(),

                TextColumn::make('position.name')
                    ->label('Cargo')
                    ->description(fn(Employee $record) => $record->position?->department?->name)
                    ->icon('heroicon-o-briefcase')
                    ->sortable()
                    ->searchable()
                    ->wrap()
                    ->toggleable(),

                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-office-2')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('employment_type')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'full_time' => 'success',
                        'day_laborer' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'full_time' => 'heroicon-o-clock',
                        'day_laborer' => 'heroicon-o-calendar-days',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'full_time' => 'Tiempo Completo',
                        'day_laborer' => 'Jornalero',
                        default => $state,
                    })
                    ->sortable()
                    ->searchable(),

                TextColumn::make('salary_display')
                    ->label('Remuneración')
                    ->getStateUsing(function (Employee $record) {
                        if ($record->employment_type === 'full_time' && $record->base_salary) {
                            return '₲ ' . number_format($record->base_salary, 0, ',', '.');
                        }
                        if ($record->employment_type === 'day_laborer' && $record->daily_rate) {
                            return '₲ ' . number_format($record->daily_rate, 0, ',', '.') . '/día';
                        }
                        return 'No especificado';
                    })
                    ->description(
                        fn(Employee $record) =>
                        $record->payroll_type ? match ($record->payroll_type) {
                            'monthly' => 'Nómina mensual',
                            'biweekly' => 'Nómina quincenal',
                            'weekly' => 'Nómina semanal',
                            default => '',
                        } : ''
                    )
                    ->icon('heroicon-o-banknotes')
                    ->sortable(['base_salary', 'daily_rate'])
                    ->toggleable(),

                TextColumn::make('contact')
                    ->label('Contacto')
                    ->getStateUsing(fn(Employee $record) => $record->phone ?: $record->email ?: 'Sin datos')
                    ->icon(fn(Employee $record) => $record->phone ? 'heroicon-o-phone' : 'heroicon-o-envelope')
                    ->url(
                        fn(Employee $record): ?string =>
                        $record->phone
                            ? 'https://api.whatsapp.com/send?phone=595' . $record->phone
                            : ($record->email ? 'mailto:' . $record->email : null)
                    )
                    ->openUrlInNewTab()
                    ->color('info')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'suspended' => 'warning',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'inactive' => 'heroicon-o-x-circle',
                        'suspended' => 'heroicon-o-pause-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
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
                    ->getStateUsing(fn(Employee $record) => filled($record->face_descriptor))
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(
                        fn(Employee $record) =>
                        filled($record->face_descriptor)
                            ? 'Rostro registrado'
                            : 'Sin rostro registrado'
                    )
                    ->alignCenter(),

                TextColumn::make('schedule.name')
                    ->label('Horario')
                    ->icon('heroicon-o-clock')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('hire_date')
                    ->label('Antigüedad')
                    ->date('d/m/Y')
                    ->description(
                        fn(Employee $record) =>
                        $record->hire_date->diffForHumans(null, true) . ' en la empresa'
                    )
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->date('d/m/Y')
                    ->description(
                        fn(Employee $record) =>
                        $record->birth_date ? $record->birth_date->age . ' años' : ''
                    )
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ci')
                    ->label('CI')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('CI copiada')
                    ->copyMessageDuration(1500)
                    ->icon('heroicon-o-identification')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->formatStateUsing(fn(string $state): string => '+595 ' . $state)
                    ->icon('heroicon-o-phone')
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->icon('heroicon-o-envelope')
                    ->copyable()
                    ->copyMessage('Email copiado')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_method')
                    ->label('Método de pago')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'debit' => 'success',
                        'cash' => 'warning',
                        'check' => 'info',
                        default => 'gray',
                    })
                    ->icon(fn(string $state): string => match ($state) {
                        'debit' => 'heroicon-o-credit-card',
                        'cash' => 'heroicon-o-banknotes',
                        'check' => 'heroicon-o-document-text',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'debit' => 'Tarjeta de Débito',
                        'cash' => 'Efectivo',
                        'check' => 'Cheque',
                        default => $state,
                    })
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since()
                    ->description(fn(Employee $record) => $record->created_at->format('d/m/Y H:i'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->since()
                    ->description(fn(Employee $record) => $record->updated_at->format('d/m/Y H:i'))
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
                    ->placeholder('Todos los estados')
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('employment_type')
                    ->label('Tipo de empleo')
                    ->options([
                        'full_time' => 'Tiempo Completo',
                        'day_laborer' => 'Jornalero',
                    ])
                    ->placeholder('Todos los tipos')
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->placeholder('Todas las sucursales')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('position_id')
                    ->label('Cargo')
                    ->relationship('position', 'name')
                    ->placeholder('Todos los cargos')
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('payroll_type')
                    ->label('Tipo de nómina')
                    ->options([
                        'monthly' => 'Mensual',
                        'biweekly' => 'Quincenal',
                        'weekly' => 'Semanal',
                    ])
                    ->placeholder('Todos los tipos')
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('payment_method')
                    ->label('Método de pago')
                    ->options([
                        'debit' => 'Tarjeta de Débito',
                        'cash' => 'Efectivo',
                        'check' => 'Cheque',
                    ])
                    ->placeholder('Todos los métodos')
                    ->native(false)
                    ->multiple(),

                Filter::make('has_face_descriptor')
                    ->label('Con rostro registrado')
                    ->query(fn(Builder $query) => $query->whereNotNull('face_descriptor'))
                    ->toggle(),

                Filter::make('without_face_descriptor')
                    ->label('Sin rostro registrado')
                    ->query(fn(Builder $query) => $query->whereNull('face_descriptor'))
                    ->toggle(),

                Filter::make('hire_date')
                    ->label('Fecha de contratación')
                    ->form([
                        DatePicker::make('hired_from')
                            ->label('Desde')
                            ->native(false)
                            ->closeOnDateSelection(),
                        DatePicker::make('hired_until')
                            ->label('Hasta')
                            ->native(false)
                            ->closeOnDateSelection(),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['hired_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('hire_date', '>=', $date),
                            )
                            ->when(
                                $data['hired_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('hire_date', '<=', $date),
                            );
                    }),

                Filter::make('created_at')
                    ->label('Fecha de registro')
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
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Ver')
                    ->color('info'),

                Tables\Actions\EditAction::make()
                    ->label('Editar'),

                Tables\Actions\Action::make('capture_face')
                    ->label(
                        fn(Employee $record): string =>
                        filled($record->face_descriptor) ? 'Actualizar rostro' : 'Capturar rostro'
                    )
                    ->icon('heroicon-o-camera')
                    ->url(fn(Employee $record): string => route('face.capture', $record))
                    ->color(
                        fn(Employee $record): string =>
                        filled($record->face_descriptor) ? 'warning' : 'success'
                    )
                    ->tooltip(
                        fn(Employee $record): string =>
                        filled($record->face_descriptor)
                            ? 'Actualizar el rostro registrado'
                            : 'Ir a captura facial'
                    )
                    ->visible(fn(Employee $record): bool => $record->status === 'active'),

                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->modalHeading('Eliminar empleado')
                    ->modalDescription('¿Estás seguro de que deseas eliminar este empleado? Esta acción no se puede deshacer.')
                    ->before(function (Employee $record) {
                        // Eliminar la foto si existe
                        if ($record->photo && Storage::disk('public')->exists($record->photo)) {
                            Storage::disk('public')->delete($record->photo);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activar seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn(Collection $records) => $records->each->update(['status' => 'active']))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('suspend')
                        ->label('Suspender seleccionados')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(fn(Collection $records) => $records->each->update(['status' => 'suspended']))
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Desactivar seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn(Collection $records) => $records->each->update(['status' => 'inactive']))
                        ->deselectRecordsAfterCompletion(),

                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->except([
                                    'photo',
                                    'face_descriptor',
                                ])
                                ->withFilename('empleados_' . now()->format('d_m_Y_H_i_s')),
                        ])
                        ->label('Exportar seleccionados')
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('Eliminar empleados')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estos empleados? Esta acción no se puede deshacer.')
                        ->before(function (Collection $records) {
                            // Eliminar las fotos de los registros
                            foreach ($records as $record) {
                                if ($record->photo && Storage::disk('public')->exists($record->photo)) {
                                    Storage::disk('public')->delete($record->photo);
                                }
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s') // Actualizar cada 30 segundos
            ->striped()
            ->emptyStateHeading('No hay empleados registrados')
            ->emptyStateDescription('Comienza agregando tu primer empleado al sistema')
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('Agregar primer empleado')
                    ->icon('heroicon-o-plus-circle'),
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
