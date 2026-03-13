<?php

namespace App\Filament\Resources;

use App\Models\Employee;
use App\Models\FaceEnrollment;
use App\Settings\GeneralSettings;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Illuminate\Support\Collection;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Actions\BulkActionGroup;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use App\Filament\Resources\EmployeeResource\Pages;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Filament\Resources\EmployeeResource\RelationManagers;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;
    protected static ?string $navigationLabel = 'Empleados';
    protected static ?string $label = 'empleado';
    protected static ?string $pluralLabel = 'empleados';
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationGroup = 'Empleados';
    protected static ?int $navigationSort = 1;

    /**
     * Define el formulario para crear y editar empleados.
     *
     * @param Form $form
     * @return Form
     */
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
                                    ->helperText('Tamaño máximo 2 MB (jpg, jpeg, png).')
                                    ->nullable()
                                    ->columnSpan(1),

                                Grid::make(1)
                                    ->schema([
                                        TextInput::make('ci')
                                            ->label('Cédula de Identidad')
                                            ->placeholder('Ej: 1234567')
                                            ->integer()
                                            ->minValue(1)
                                            ->maxValue(999999999999999)
                                            ->step(1)
                                            ->required()
                                            ->unique(Employee::class, 'ci', ignoreRecord: true),

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
                                    ->nullable(),

                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->prefix('+595')
                                    ->placeholder('971123456')
                                    ->minLength(7)
                                    ->maxLength(30)
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

                Section::make('Asignaciones')
                    ->description('Sucursal y horario del empleado')
                    ->icon('heroicon-o-building-office')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
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
                                    ->hiddenOn('create'),

                                Placeholder::make('status_info')
                                    ->label('Estado')
                                    ->content('El empleado se creará con estado "Activo" por defecto')
                                    ->visibleOn('create'),

                                Textarea::make('face_descriptor')
                                    ->label('Descriptor facial')
                                    ->rows(1)
                                    ->maxLength(5000)
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Datos biométricos del rostro (generado automáticamente por el sistema)')
                                    ->hiddenOn('create'),
                            ]),
                    ]),

                Section::make('Protección de Maternidad')
                    ->description('Ley N° 5508/15 — Protección durante el embarazo y hasta el primer año de vida del hijo')
                    ->icon('heroicon-o-heart')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                DatePicker::make('maternity_protection_until')
                                    ->label('Protegida hasta')
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    ->minDate(now()->subYears(1))
                                    ->nullable()
                                    ->helperText('Fecha hasta la que aplica la protección (normalmente 1 año desde el nacimiento del hijo). Dejar vacío si no aplica.')
                                    ->columnSpan(1),

                                Placeholder::make('maternity_info')
                                    ->label('¿Qué implica este campo?')
                                    ->content('Si esta fecha es hoy o posterior, el sistema mostrará una advertencia al intentar crear una liquidación para esta empleada. La protección no impide el proceso — es solo un aviso legal.')
                                    ->columnSpan(1),
                            ]),
                    ]),
            ])
            ->columns(1);
    }

    /**
     * Define la tabla para listar empleados.
     *
     * @param Table $table
     * @return Table
     */
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

                TextColumn::make('activeContract.position.name')
                    ->label('Cargo')
                    ->icon('heroicon-o-briefcase')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->wrap()
                    ->badge()
                    ->color('primary')
                    ->default('Sin contrato'),

                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->icon('heroicon-o-building-office-2')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap()
                    ->badge()
                    ->color('info'),

                TextColumn::make('employment_type')
                    ->label('Tipo')
                    ->icon(fn(Employee $record): string => $record->employment_type_icon ?? 'heroicon-o-question-mark-circle')
                    ->color(fn(Employee $record): string => $record->employment_type_color ?? 'gray')
                    ->formatStateUsing(fn(Employee $record): string => $record->employment_type_label ?? 'Sin contrato')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('activeContract.salary')
                    ->label('Salario')
                    ->icon('heroicon-o-banknotes')
                    ->formatStateUsing(
                        fn($state, Employee $record): string =>
                        $state
                            ? 'Gs. ' . number_format((int) $state, 0, ',', '.') . ($record->activeContract?->salary_type === 'jornal' ? '/día' : '/mes')
                            : 'Sin contrato'
                    )
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('activeContract.payroll_type')
                    ->label('Nómina')
                    ->icon('heroicon-o-calendar')
                    ->formatStateUsing(fn($state): string => \App\Models\Employee::getPayrollTypeOptions()[$state] ?? 'Sin contrato')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('contact')
                    ->label('Teléfono')
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
                    ->toggleable(isToggledHiddenByDefault: true),

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
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('payment_method')
                    ->label('Método de pago')
                    ->icon(fn(Employee $record): string => $record->payment_method_icon)
                    ->color(fn(Employee $record): string => $record->payment_method_color)
                    ->formatStateUsing(fn(Employee $record): string => $record->payment_method_label)
                    ->badge()
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
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }
                        $salaryType = $data['value'] === 'day_laborer' ? 'jornal' : 'mensual';
                        return $query->whereHas('activeContract', fn($q) => $q->where('salary_type', $salaryType));
                    }),

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
                    ->options(fn() => \App\Models\Position::getOptionsWithDepartment())
                    ->placeholder('Todos los cargos')
                    ->searchable()
                    ->native(false)
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }
                        return $query->whereHas('activeContract', fn($q) => $q->where('position_id', $data['value']));
                    }),

                SelectFilter::make('payroll_type')
                    ->label('Tipo de nómina')
                    ->options(Employee::getPayrollTypeOptions())
                    ->placeholder('Todos los tipos')
                    ->native(false)
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }
                        return $query->whereHas('activeContract', fn($q) => $q->where('payroll_type', $data['value']));
                    }),

                SelectFilter::make('payment_method')
                    ->label('Método de pago')
                    ->options(Employee::getPaymentMethodOptions())
                    ->placeholder('Todos los métodos')
                    ->native(false)
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['values'])) {
                            return $query;
                        }
                        return $query->whereHas('activeContract', fn($q) => $q->whereIn('payment_method', $data['values']));
                    }),

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
                                fn(Builder $query, $date): Builder => $query->whereHas('activeContract', fn($q) => $q->whereDate('start_date', '>=', $date)),
                            )
                            ->when(
                                $data['hired_until'],
                                fn(Builder $query, $date): Builder => $query->whereHas('activeContract', fn($q) => $q->whereDate('start_date', '<=', $date)),
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
                EditAction::make(),

                Action::make('capture_face')
                    ->label(fn(Employee $record): string => $record->has_face ? 'Actualizar rostro' : 'Capturar rostro')
                    ->icon('heroicon-o-camera')
                    ->url(fn(Employee $record): string => route('face.capture', $record))
                    ->color(fn(Employee $record): string => $record->has_face ? 'warning' : 'success')
                    ->tooltip(fn(Employee $record): string => $record->has_face ? 'Actualizar el rostro registrado' : 'Ir a captura facial')
                    ->visible(fn(Employee $record): bool => $record->status === 'active'),

                Action::make('generate_enrollment')
                    ->label('Enlace de Registro')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->tooltip('Generar enlace para que el empleado registre su rostro')
                    ->visible(fn(Employee $record): bool => $record->status === 'active')
                    ->requiresConfirmation()
                    ->modalHeading('Generar Enlace de Registro Facial')
                    ->modalDescription(fn(Employee $record) => "Se generará un enlace temporal para que {$record->first_name} {$record->last_name} registre su rostro. El enlace expirará en " . app(GeneralSettings::class)->face_enrollment_expiry_hours . " horas.")
                    ->modalSubmitActionLabel('Generar Enlace')
                    ->action(function (Employee $record) {
                        $settings = app(GeneralSettings::class);
                        $enrollment = FaceEnrollment::createForEmployee(
                            $record,
                            Auth::id(),
                            $settings->face_enrollment_expiry_hours
                        );

                        $url = route('face-enrollment.show', $enrollment->token);

                        Notification::make()
                            ->success()
                            ->title('Enlace Generado')
                            ->body($url)
                            ->persistent()
                            ->actions([
                                \Filament\Notifications\Actions\Action::make('open')
                                    ->label('Abrir Enlace')
                                    ->url($url)
                                    ->openUrlInNewTab(),
                                \Filament\Notifications\Actions\Action::make('whatsapp')
                                    ->label('Enviar por WhatsApp')
                                    ->url("https://api.whatsapp.com/send?phone=595" . preg_replace('/\D/', '', $record->phone ?? '') . "&text=" . urlencode("Hola {$record->first_name}, usa este enlace para registrar tu rostro: {$url}"))
                                    ->openUrlInNewTab()
                                    ->visible(fn() => filled($record->phone)),
                            ])
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activar')
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
                        ->label('Suspender')
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
                        ->label('Desactivar')
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
                        ->label('Exportar a Excel')
                        ->color('info')
                        ->icon('heroicon-o-arrow-down-tray'),
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
            RelationManagers\ContractsRelationManager::class,
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
