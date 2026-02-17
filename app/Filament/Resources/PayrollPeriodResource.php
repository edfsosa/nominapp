<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayrollPeriodResource\Pages;
use App\Filament\Resources\PayrollPeriodResource\RelationManagers\PayrollsRelationManager;
use App\Models\PayrollPeriod;
use App\Services\PayrollService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PayrollPeriodResource extends Resource
{
    protected static ?string $model = PayrollPeriod::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Períodos';
    protected static ?string $label = 'Período';
    protected static ?string $pluralLabel = 'Períodos';
    protected static ?string $slug = 'periodos';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Período')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->placeholder('Ejemplo: Enero 2024')
                            ->maxLength(255)
                            ->columnSpan(2),

                        Select::make('frequency')
                            ->label('Frecuencia')
                            ->options([
                                'monthly'  => 'Mensual',
                                'biweekly' => 'Quincenal',
                                'weekly'   => 'Semanal',
                            ])
                            ->native(false)
                            ->required()
                            ->reactive()
                            ->columnSpan(1),
                    ])
                    ->columns(3),

                Section::make('Fechas del Período')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Fecha de Inicio')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn($state, callable $set) => $set('end_date', null))
                            ->columnSpan(1),

                        DatePicker::make('end_date')
                            ->label('Fecha de Fin')
                            ->displayFormat('d/m/Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->required()
                            ->minDate(fn($get) => $get('start_date'))
                            ->disabled(fn($get) => !$get('start_date'))
                            ->helperText('La fecha de fin debe ser posterior a la fecha de inicio')
                            ->rules([
                                function ($get) {
                                    return function (string $attribute, $value, \Closure $fail) use ($get) {
                                        $query = \App\Models\PayrollPeriod::where('frequency', $get('frequency'))
                                            ->where('start_date', $get('start_date'))
                                            ->where('end_date', $value);

                                        // Obtener el ID del registro actual si existe (cuando editamos)
                                        $recordId = $get('id');
                                        if ($recordId) {
                                            $query->where('id', '!=', $recordId);
                                        }

                                        if ($query->exists()) {
                                            $fail('Ya existe un período con esta frecuencia y fechas.');
                                        }
                                    };
                                },
                            ])
                            ->columnSpan(1),
                    ])
                    ->columns(2),

                Section::make('Estado y Notas')
                    ->schema([
                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'draft'      => 'Borrador',
                                'processing' => 'En Proceso',
                                'closed'     => 'Cerrado',
                            ])
                            ->native(false)
                            ->default('draft')
                            ->required()
                            ->columnSpan(1),

                        DateTimePicker::make('closed_at')
                            ->label('Fecha de Cierre')
                            ->displayFormat('d/m/Y H:i')
                            ->native(false)
                            ->disabled()
                            ->helperText('Se establece automáticamente al cerrar el período')
                            ->columnSpan(1),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->placeholder('Observaciones o comentarios sobre este período')
                            ->rows(3)
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-calendar-days')
                    ->iconColor('primary'),

                TextColumn::make('frequency')
                    ->label('Frecuencia')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'monthly'  => 'Mensual',
                        'biweekly' => 'Quincenal',
                        'weekly'   => 'Semanal',
                        default    => $state,
                    })
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('payrolls_count')
                    ->label('Recibos')
                    ->counts('payrolls')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft'      => 'gray',
                        'processing' => 'warning',
                        'closed'     => 'success',
                        default      => 'primary',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'draft'      => 'Borrador',
                        'processing' => 'En Proceso',
                        'closed'     => 'Cerrado',
                        default      => $state,
                    })
                    ->sortable(),

                TextColumn::make('closed_at')
                    ->label('Cerrado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
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
                        'draft'      => 'Borrador',
                        'processing' => 'En Proceso',
                        'closed'     => 'Cerrado',
                    ])
                    ->native(false),

                SelectFilter::make('frequency')
                    ->label('Frecuencia')
                    ->options([
                        'monthly'  => 'Mensual',
                        'biweekly' => 'Quincenal',
                        'weekly'   => 'Semanal',
                    ])
                    ->native(false),

                Filter::make('current_year')
                    ->label('Año Actual')
                    ->query(fn($query) => $query->whereYear('start_date', now()->year))
                    ->default(),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('generate_payrolls')
                    ->label('Generar Recibos')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generar Recibos de Nómina')
                    ->modalDescription(
                        fn(PayrollPeriod $record) =>
                        "¿Está seguro de generar los recibos de nómina para el período {$record->name}? " .
                            "Esta acción creará recibos para todos los empleados activos."
                    )
                    ->action(function (PayrollPeriod $record, PayrollService $payrollService) {
                        $count = $payrollService->generateForPeriod($record);

                        if ($count > 0) {
                            $record->update([
                                'status' => 'processing',
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Recibos generados')
                                ->body("Se generaron exitosamente {$count} recibos de nómina.")
                                ->send();
                        } else {
                            Notification::make()
                                ->warning()
                                ->title('No se generaron recibos')
                                ->body('Es posible que ya hayan sido generados o que no haya empleados activos.')
                                ->send();
                        }
                    })
                    ->visible(fn(PayrollPeriod $record) => in_array($record->status, ['draft', 'processing']) && !$record->payrolls()->exists()),

                Action::make('regenerate_payrolls')
                    ->label('Regenerar Recibos')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerar Todos los Recibos')
                    ->modalDescription(
                        fn(PayrollPeriod $record) =>
                        "¿Está seguro de regenerar TODOS los recibos del período {$record->name}? " .
                            "Esta acción recalculará percepciones, deducciones, horas extras, ausencias y cuotas de préstamos para cada empleado. Solo se regenerarán los recibos en estado borrador."
                    )
                    ->action(function (PayrollPeriod $record, PayrollService $payrollService) {
                        $payrolls = $record->payrolls()->where('status', 'draft')->with('employee')->get();

                        if ($payrolls->isEmpty()) {
                            Notification::make()
                                ->warning()
                                ->title('Sin recibos para regenerar')
                                ->body('No hay recibos en estado borrador para regenerar.')
                                ->send();
                            return;
                        }

                        $count = 0;
                        $errors = 0;

                        foreach ($payrolls as $payroll) {
                            try {
                                $payrollService->regenerateForEmployee($payroll);
                                $count++;
                            } catch (\Throwable $e) {
                                $errors++;
                            }
                        }

                        if ($errors > 0) {
                            Notification::make()
                                ->warning()
                                ->title("Regeneración parcial")
                                ->body("Se regeneraron {$count} recibos. {$errors} recibos tuvieron errores. Revise el log para más detalles.")
                                ->duration(10000)
                                ->send();
                        } else {
                            Notification::make()
                                ->success()
                                ->title('Recibos regenerados')
                                ->body("Se regeneraron exitosamente {$count} recibos de nómina.")
                                ->send();
                        }
                    })
                    ->visible(fn(PayrollPeriod $record) => $record->status === 'processing' && $record->payrolls()->where('status', 'draft')->exists()),

                Action::make('close_period')
                    ->label('Cerrar Período')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cerrar Período de Nómina')
                    ->modalDescription(fn(PayrollPeriod $record) =>
                        "¿Está seguro de cerrar el período {$record->name}? Una vez cerrado, no se podrán generar más recibos para este período."
                    )
                    ->before(function (PayrollPeriod $record, Action $action) {
                        $draftPayrolls = $record->payrolls()->where('status', 'draft')->count();

                        if ($draftPayrolls > 0) {
                            Notification::make()
                                ->danger()
                                ->title('No se puede cerrar el período')
                                ->body("Hay {$draftPayrolls} recibos en estado borrador. Apruebe todos los recibos antes de cerrar el período.")
                                ->duration(10000)
                                ->send();

                            $action->cancel();
                        }
                    })
                    ->action(function (PayrollPeriod $record) {
                        $record->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Período cerrado')
                            ->body("El período {$record->name} ha sido cerrado exitosamente.")
                            ->send();
                    })
                    ->visible(fn(PayrollPeriod $record) => $record->status === 'processing'),

                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn(PayrollPeriod $record) => $record->status === 'draft'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->action(function ($records) {
                            $deleted = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'draft') {
                                    $record->delete();
                                    $deleted++;
                                }
                            }

                            if ($deleted > 0) {
                                Notification::make()
                                    ->success()
                                    ->title("Se eliminaron {$deleted} períodos en borrador")
                                    ->send();
                            } else {
                                Notification::make()
                                    ->warning()
                                    ->title('Solo se pueden eliminar períodos en borrador')
                                    ->send();
                            }
                        }),
                ]),
            ])
            ->defaultSort('start_date', 'desc')
            ->emptyStateHeading('No hay períodos de nómina registrados')
            ->emptyStateDescription('Comienza a crear períodos de nómina para gestionar los pagos de los empleados.')
            ->emptyStateIcon('heroicon-o-calendar-days');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Información del Período')
                    ->schema([
                        Group::make([
                            TextEntry::make('name')
                                ->label('Nombre')
                                ->icon('heroicon-o-calendar-days'),

                            TextEntry::make('frequency')
                                ->label('Frecuencia')
                                ->badge()
                                ->color('info')
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'monthly'  => 'Mensual',
                                    'biweekly' => 'Quincenal',
                                    'weekly'   => 'Semanal',
                                    default    => $state,
                                }),
                        ])->columns(2),

                        Group::make([
                            TextEntry::make('start_date')
                                ->label('Fecha de Inicio')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-calendar'),

                            TextEntry::make('end_date')
                                ->label('Fecha de Fin')
                                ->date('d/m/Y')
                                ->icon('heroicon-o-calendar'),
                        ])->columns(2),
                    ]),

                InfolistSection::make('Estado del Período')
                    ->schema([
                        Group::make([
                            TextEntry::make('status')
                                ->label('Estado')
                                ->badge()
                                ->color(fn(string $state): string => match ($state) {
                                    'draft'      => 'gray',
                                    'processing' => 'warning',
                                    'closed'     => 'success',
                                    default      => 'primary',
                                })
                                ->formatStateUsing(fn(string $state): string => match ($state) {
                                    'draft'      => 'Borrador',
                                    'processing' => 'En Proceso',
                                    'closed'     => 'Cerrado',
                                    default      => $state,
                                }),

                            TextEntry::make('closed_at')
                                ->label('Fecha de Cierre')
                                ->dateTime('d/m/Y H:i')
                                ->icon('heroicon-o-lock-closed')
                                ->placeholder('No cerrado'),
                        ])->columns(2),

                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas')
                            ->columnSpanFull(),
                    ]),

                InfolistSection::make('Información del Sistema')
                    ->schema([
                        Group::make([
                            TextEntry::make('created_at')
                                ->label('Creado')
                                ->dateTime('d/m/Y H:i'),

                            TextEntry::make('updated_at')
                                ->label('Actualizado')
                                ->dateTime('d/m/Y H:i'),
                        ])->columns(2),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PayrollsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayrollPeriods::route('/'),
            'create' => Pages\CreatePayrollPeriod::route('/create'),
            'view' => Pages\ViewPayrollPeriod::route('/{record}'),
            'edit' => Pages\EditPayrollPeriod::route('/{record}/edit'),
        ];
    }
}
