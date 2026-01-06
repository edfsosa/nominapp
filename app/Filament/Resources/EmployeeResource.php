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
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
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
                                    ->required(),
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
                                    ->options(Employee::getEmploymentTypeOptions())
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
                                    ->options(Employee::getPayrollTypeOptions())
                                    ->native(false)
                                    ->default('monthly')
                                    ->required(),

                                Select::make('payment_method')
                                    ->label('Método de pago')
                                    ->options(Employee::getPaymentMethodOptions())
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
                                    ->step(1)
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
                                    ->step(1)
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
                                    ->options(\App\Models\Position::getOptionsWithDepartment())
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
                                    ->options(Employee::getStatusOptions())
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
                    ->defaultImageUrl(fn(Employee $record): string => $record->photo_url)
                    ->size(40)
                    ->toggleable(),

                TextColumn::make('full_name')
                    ->label('Nombre Completo')
                    ->getStateUsing(fn(Employee $record): string => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->wrap(),

                TextColumn::make('ci')
                    ->label('CI')
                    ->icon('heroicon-o-identification')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->tooltip('Haz clic para copiar')
                    ->copyMessage('Cédula copiada'),

                TextColumn::make('position.name')
                    ->label('Cargo')
                    ->icon('heroicon-o-briefcase')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->wrap()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-office-2')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->wrap()
                    ->badge()
                    ->color('info'),

                TextColumn::make('employment_type')
                    ->label('Tipo')
                    ->icon(fn(Employee $record): string => $record->employment_type_icon)
                    ->color(fn(Employee $record): string => $record->employment_type_color)
                    ->formatStateUsing(fn(Employee $record): string => $record->employment_type_label)
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('contact')
                    ->label('Contacto')
                    ->icon('heroicon-o-phone')
                    ->getStateUsing(fn(Employee $record): string => $record->contact_text)
                    ->url(fn(Employee $record): ?string => $record->contact_url)
                    ->openUrlInNewTab()
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->icon(fn(Employee $record): string => $record->status_icon)
                    ->color(fn(Employee $record): string => $record->status_color)
                    ->formatStateUsing(fn(Employee $record): string => $record->status_label)
                    ->badge()
                    ->sortable()
                    ->searchable(),

                IconColumn::make('has_face')
                    ->label('Rostro')
                    ->boolean()
                    ->getStateUsing(fn(Employee $record): bool => $record->has_face)
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->tooltip(fn(Employee $record): string => $record->face_tooltip)
                    ->alignCenter(),

                TextColumn::make('hire_date')
                    ->label('Antigüedad')
                    ->date('d/m/Y')
                    ->description(fn(Employee $record): string => $record->antiquity_description)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->date('d/m/Y')
                    ->description(fn(Employee $record): string => $record->age_description)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->icon('heroicon-o-envelope')
                    ->copyable()
                    ->copyMessage('Email copiado')
                    ->tooltip('Haz clic para copiar')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('payment_method')
                    ->label('Método de pago')
                    ->icon(fn(Employee $record): string => $record->payment_method_icon)
                    ->color(fn(Employee $record): string => $record->payment_method_color)
                    ->formatStateUsing(fn(Employee $record): string => $record->payment_method_label)
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at_description')
                    ->label('Registrado')
                    ->sortable()
                    ->description(fn(Employee $record): string => $record->created_at_since)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at_description')
                    ->label('Última actualización')
                    ->sortable()
                    ->description(fn(Employee $record): string => $record->updated_at_since)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('employment_type')
                    ->label('Tipo de empleo')
                    ->options(Employee::getEmploymentTypeOptions())
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
                    ->options(Employee::getPayrollTypeOptions())
                    ->placeholder('Todos los tipos')
                    ->native(false)
                    ->multiple(),

                SelectFilter::make('payment_method')
                    ->label('Método de pago')
                    ->options(Employee::getPaymentMethodOptions())
                    ->placeholder('Todos los métodos')
                    ->native(false)
                    ->multiple(),

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

                SelectFilter::make('birthday_month')
                    ->label('Mes de Cumpleaños')
                    ->options(Employee::getMonthOptions())
                    ->query(function ($query, array $data) {
                        if (filled($data['value'])) {
                            return $query->whereMonth('birth_date', $data['value']);
                        }
                    })
                    ->native(false),

            ])
            ->actions([
                ViewAction::make(),

                EditAction::make(),

                Action::make('capture_face')
                    ->label(fn(Employee $record): string => $record->has_face ? 'Actualizar rostro' : 'Capturar rostro')
                    ->icon('heroicon-o-camera')
                    ->url(fn(Employee $record): string => route('face.capture', $record))
                    ->color(fn(Employee $record): string => $record->has_face ? 'warning' : 'success')
                    ->tooltip(fn(Employee $record): string => $record->has_face ? 'Actualizar el rostro registrado' : 'Ir a captura facial')
                    ->visible(fn(Employee $record): bool => $record->status === 'active'),

                DeleteAction::make()
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
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activar seleccionados')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activar empleados')
                        ->modalDescription('¿Estás seguro de que deseas activar estos empleados?')
                        ->action(function (Collection $records): void {
                            $count = $records->count();
                            $records->each->update(['status' => 'active']);
                            Notification::make()
                                ->success()
                                ->title('Empleados activados')
                                ->body("{$count} empleado(s) activado(s) correctamente.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('suspend')
                        ->label('Suspender seleccionados')
                        ->icon('heroicon-o-pause-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Suspender empleados')
                        ->modalDescription('¿Estás seguro de que deseas suspender estos empleados?')
                        ->action(function (Collection $records): void {
                            $count = $records->count();
                            $records->each->update(['status' => 'suspended']);
                            Notification::make()
                                ->warning()
                                ->title('Empleados suspendidos')
                                ->body("{$count} empleado(s) suspendido(s) correctamente.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate')
                        ->label('Desactivar seleccionados')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Desactivar empleados')
                        ->modalDescription('¿Estás seguro de que deseas desactivar estos empleados?')
                        ->action(function (Collection $records): void {
                            $count = $records->count();
                            $records->each->update(['status' => 'inactive']);
                            Notification::make()
                                ->danger()
                                ->title('Empleados desactivados')
                                ->body("{$count} empleado(s) desactivado(s) correctamente.")
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    ExportBulkAction::make()
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->except(['photo', 'face_descriptor', 'has_face'])
                                ->withFilename('empleados_' . now()->format('d_m_Y_H_i_s')),
                        ])
                        ->label('Exportar seleccionados')
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),

                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('Eliminar empleados')
                        ->modalDescription('¿Estás seguro de que deseas eliminar estos empleados? Esta acción no se puede deshacer.')
                        ->before(function (Collection $records): void {
                            foreach ($records as $record) {
                                if ($record->photo && Storage::disk('public')->exists($record->photo)) {
                                    Storage::disk('public')->delete($record->photo);
                                }
                            }
                        })
                        ->successNotificationTitle(fn(Collection $records): string => "{$records->count()} empleado(s) eliminado(s) correctamente"),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('No hay empleados registrados')
            ->emptyStateDescription('Comienza agregando tu primer empleado al sistema')
            ->emptyStateIcon('heroicon-o-users');
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
