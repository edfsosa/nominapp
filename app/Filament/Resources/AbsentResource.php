<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\Absent;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\AttendanceDay;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Actions\DeleteBulkAction;
use App\Filament\Resources\AbsentResource\Pages;

class AbsentResource extends Resource
{
    protected static ?string $model = Absent::class;
    protected static ?string $navigationLabel = 'Ausencias';
    protected static ?string $label = 'ausencia';
    protected static ?string $pluralLabel = 'ausencias';
    protected static ?string $slug = 'ausencias';
    protected static ?string $navigationIcon = 'heroicon-o-x-circle';
    protected static ?string $navigationGroup = 'Asistencias';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Empleado')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship(
                                name: 'employee',
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->orderBy('first_name')
                                    ->orderBy('last_name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->full_name_with_ci)
                            ->searchable(['first_name', 'last_name', 'ci'])
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('attendance_day_id', null))
                            ->disabled(fn(string $operation) => $operation === 'edit'),

                        Select::make('attendance_day_id')
                            ->label('Día de Asistencia')
                            ->options(function (Get $get) {
                                $employeeId = $get('employee_id');

                                if (!$employeeId) {
                                    return [];
                                }

                                return AttendanceDay::query()
                                    ->where('employee_id', $employeeId)
                                    ->where('status', 'absent')
                                    ->whereDoesntHave('absent') // Solo mostrar días sin registro de ausencia
                                    ->orderBy('date', 'desc')
                                    ->limit(50) // Limitar a los últimos 50 días
                                    ->get()
                                    ->pluck('date_formatted', 'id');
                            })
                            ->getOptionLabelUsing(fn($value): ?string => AttendanceDay::find($value)?->date_formatted)
                            ->searchable()
                            ->native(false)
                            ->required()
                            ->disabled(fn(string $operation, Get $get) => $operation === 'edit' || !$get('employee_id'))
                            ->helperText(fn(Get $get) => !$get('employee_id')
                                ? 'Primero selecciona un empleado'
                                : 'Solo se muestran días con ausencia que no tienen registro'),
                    ])
                    ->columns(2),

                Section::make('Estado de la Ausencia')
                    ->schema([
                        Select::make('status')
                            ->label('Estado')
                            ->options(Absent::getStatusOptions())
                            ->required()
                            ->native(false)
                            ->default('pending')
                            ->live()
                            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                if ($state === 'justified') {
                                    $set('reviewed_at', now());
                                    $set('reviewed_by_id', Auth::id());
                                } elseif ($state === 'unjustified') {
                                    $set('reviewed_at', now());
                                    $set('reviewed_by_id', Auth::id());
                                }
                            }),

                        Textarea::make('reason')
                            ->label('Motivo de la Ausencia')
                            ->placeholder('Ingrese el motivo de la ausencia...')
                            ->rows(1)
                            ->nullable(),

                        FileUpload::make('documents')
                            ->label('Documentos Justificativos')
                            ->multiple()
                            ->directory('absents-documents')
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(5120) // 5MB
                            ->columnSpanFull()
                            ->helperText('Sube documentos que justifiquen la ausencia (máx. 5MB por archivo).'),
                    ])
                    ->columns(2),

                Section::make('Revisión')
                    ->schema([
                        DateTimePicker::make('reported_at')
                            ->label('Fecha de Reporte')
                            ->default(now())
                            ->required()
                            ->native(false)
                            ->disabled(),

                        Select::make('reported_by_id')
                            ->label('Reportado por')
                            ->relationship('reportedBy', 'name')
                            ->default(Auth::id())
                            ->native(false)
                            ->disabled(),

                        DateTimePicker::make('reviewed_at')
                            ->label('Fecha de Revisión')
                            ->native(false)
                            ->disabled(),

                        Select::make('reviewed_by_id')
                            ->label('Revisado por')
                            ->relationship('reviewedBy', 'name')
                            ->native(false)
                            ->disabled(),

                        Textarea::make('review_notes')
                            ->label('Notas de Revisión')
                            ->rows(3)
                            ->columnSpanFull(),

                        Select::make('employee_deduction_id')
                            ->label('Deducción Generada')
                            ->relationship('employeeDeduction', 'id')
                            ->native(false)
                            ->disabled()
                            ->visible(fn($record) => $record?->employee_deduction_id !== null),
                    ])
                    ->columns(2)
                    ->visible(fn(string $operation) => $operation === 'edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('attendanceDay.date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->wrap(),

                TextColumn::make('employee.ci')
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

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Absent::getStatusLabel($state))
                    ->color(fn(string $state): string => Absent::getStatusColor($state))
                    ->sortable(),

                TextColumn::make('reported_at')
                    ->label('Reportado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('reportedBy.name')
                    ->label('Reportado por')
                    ->default('Sistema')
                    ->badge()
                    ->color(fn($record) => $record->reported_by_id ? 'primary' : 'gray')
                    ->icon(fn($record) => $record->reported_by_id ? 'heroicon-o-user' : 'heroicon-o-cog-6-tooth')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('reviewed_at')
                    ->label('Revisado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('reviewedBy.name')
                    ->label('Revisado por')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('reported_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Absent::getStatusOptions())
                    ->native(false),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->full_name} (CI: {$record->ci})")
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->preload(false)
                    ->native(false)
                    ->multiple(),

                Filter::make('has_deduction')
                    ->label('Con Deducción')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('employee_deduction_id')),

                SelectFilter::make('origin')
                    ->label('Origen')
                    ->options([
                        'system' => 'Sistema (automático)',
                        'manual' => 'Manual (usuario)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function (Builder $query, string $value) {
                            if ($value === 'system') {
                                return $query->whereNull('reported_by_id');
                            }
                            return $query->whereNotNull('reported_by_id');
                        });
                    })
                    ->native(false),

                Filter::make('reported_at')
                    ->label('Fecha de Reporte')
                    ->form([
                        DatePicker::make('reported_from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        DatePicker::make('reported_until')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->columns(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['reported_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('reported_at', '>=', $date),
                            )
                            ->when(
                                $data['reported_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('reported_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                EditAction::make(),

                Action::make('justify')
                    ->label('Justificar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Absent $record) => !$record->isJustified())
                    ->requiresConfirmation()
                    ->modalHeading(fn(Absent $record) => $record->isUnjustified()
                        ? 'Cambiar a Justificada'
                        : 'Justificar Ausencia')
                    ->modalDescription(fn(Absent $record) => $record->isUnjustified()
                        ? '¿Está seguro? Esto eliminará la deducción generada previamente.'
                        : '¿Está seguro de que desea marcar esta ausencia como justificada?')
                    ->form([
                        Textarea::make('review_notes')
                            ->label('Notas de revisión')
                            ->placeholder('Motivo de la justificación...')
                            ->rows(3),
                    ])
                    ->action(function (Absent $record, array $data) {
                        $result = $record->justify(Auth::id(), $data['review_notes'] ?? null);

                        Notification::make()
                            ->success()
                            ->title('Ausencia Justificada')
                            ->body($result['message'])
                            ->send();
                    }),

                Action::make('mark_unjustified')
                    ->label('Marcar Injustificada')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Absent $record) => !$record->isUnjustified())
                    ->requiresConfirmation()
                    ->modalHeading(fn(Absent $record) => $record->isJustified()
                        ? 'Cambiar a Injustificada'
                        : 'Marcar como Injustificada')
                    ->modalDescription(fn(Absent $record) => $record->isJustified()
                        ? 'Esto generará una deducción del salario del empleado.'
                        : 'Esto generará automáticamente una deducción del salario del empleado.')
                    ->form([
                        Textarea::make('review_notes')
                            ->label('Notas de revisión')
                            ->placeholder('Motivo por el cual se marca como injustificada...')
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (Absent $record, array $data) {
                        $result = $record->markAsUnjustified(Auth::id(), $data['review_notes']);

                        Notification::make()
                            ->success()
                            ->title('Ausencia Marcada como Injustificada')
                            ->body($result['message'])
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbsents::route('/'),
            'create' => Pages\CreateAbsent::route('/create'),
            'edit' => Pages\EditAbsent::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
