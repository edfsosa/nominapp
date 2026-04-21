<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdvanceResource\Pages;
use App\Models\Advance;
use App\Models\Employee;
use App\Settings\PayrollSettings;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AdvanceResource extends Resource
{
    protected static ?string $model = Advance::class;

    protected static ?string $navigationLabel = 'Adelantos';

    protected static ?string $label = 'adelanto';

    protected static ?string $pluralLabel = 'adelantos';

    protected static ?string $slug = 'adelantos';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Nóminas';

    protected static ?int $navigationSort = 6;

    /**
     * Define el formulario de creación/edición de adelantos.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Adelanto')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Empleado')
                            ->relationship(
                                name: 'employee',
                                modifyQueryUsing: fn(Builder $query) => $query
                                    ->where('status', 'active')
                                    ->whereHas('activeContract', fn($c) => $c->whereNotNull('salary')->where('salary', '>', 0))
                                    ->orderBy('first_name')
                                    ->orderBy('last_name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->full_name_with_ci)
                            ->searchable(['first_name', 'last_name', 'ci'])
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, Get $get) {
                                $set('amount', null);

                                $employee = $get('employee_id') ? Employee::find($get('employee_id')) : null;
                                $set('max_advance_amount', $employee?->getMaxAdvanceAmount());
                            })
                            ->disabled(fn(string $operation) => $operation === 'edit')
                            ->helperText('Solo se muestran empleados activos con salario definido.'),

                        TextInput::make('amount')
                            ->label('Monto')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->maxValue(fn(Get $get) => $get('max_advance_amount') ?? 9999999999)
                            ->prefix('Gs.')
                            ->helperText(function (Get $get) {
                                $max = $get('max_advance_amount');

                                $percent = (int) app(PayrollSettings::class)->advance_max_percent;

                                return $max
                                    ? 'Máximo: ' . number_format($max, 0, ',', '.') . ' Gs. (' . $percent . '% del salario)'
                                    : 'Seleccione un empleado para ver el monto máximo';
                            })
                            ->disabled(fn(string $operation) => $operation === 'edit'),

                        Textarea::make('notes')
                            ->label('Notas')
                            ->placeholder('Motivo u observaciones...')
                            ->rows(2)
                            ->columnSpanFull(),

                        Hidden::make('max_advance_amount')->dehydrated(false),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Define el infolist de detalle de un adelanto.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Empleado')
                    ->schema([
                        Group::make([
                            TextEntry::make('employee.full_name')
                                ->label('Nombre')
                                ->icon('heroicon-o-user'),

                            TextEntry::make('employee.ci')
                                ->label('CI')
                                ->icon('heroicon-o-identification')
                                ->badge()
                                ->color('gray')
                                ->copyable(),

                            TextEntry::make('employee.activeContract.position.name')
                                ->label('Cargo')
                                ->icon('heroicon-o-briefcase')
                                ->badge()
                                ->color('info')
                                ->placeholder('-'),
                        ])->columns(3),
                    ]),

                InfolistSection::make('Datos del Adelanto')
                    ->schema([
                        Group::make([
                            TextEntry::make('amount')
                                ->label('Monto')
                                ->money('PYG', locale: 'es_PY')
                                ->icon('heroicon-o-banknotes'),

                            TextEntry::make('status')
                                ->label('Estado')
                                ->formatStateUsing(fn(string $state) => Advance::getStatusLabel($state))
                                ->color(fn(string $state) => Advance::getStatusColor($state))
                                ->icon(fn(string $state) => Advance::getStatusIcon($state))
                                ->badge(),

                            TextEntry::make('created_at')
                                ->label('Solicitado')
                                ->dateTime('d/m/Y H:i')
                                ->icon('heroicon-o-calendar'),
                        ])->columns(3),

                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('Sin notas')
                            ->columnSpanFull(),
                    ]),

                InfolistSection::make('Aprobación')
                    ->schema([
                        Group::make([
                            TextEntry::make('approved_at')
                                ->label('Fecha de Aprobación')
                                ->dateTime('d/m/Y H:i')
                                ->icon('heroicon-o-calendar')
                                ->placeholder('-'),

                            TextEntry::make('approvedBy.name')
                                ->label('Aprobado por')
                                ->icon('heroicon-o-user-circle')
                                ->placeholder('-'),

                            TextEntry::make('payroll.period.name')
                                ->label('Nómina')
                                ->icon('heroicon-o-document-text')
                                ->placeholder('Pendiente de nómina'),
                        ])->columns(3),
                    ])
                    ->visible(fn(Advance $record) => $record->isApproved() || $record->isPaid()),
            ]);
    }

    /**
     * Define la tabla de listado de adelantos.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn(Builder $query) => $query->with(['employee', 'approvedBy'])
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                ImageColumn::make('employee.photo')
                    ->label('Foto')
                    ->circular()
                    ->defaultImageUrl(fn($record) => $record->employee->avatar_url)
                    ->toggleable(),

                TextColumn::make('employee.full_name')
                    ->label('Empleado')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name', 'last_name'])
                    ->wrap(),

                TextColumn::make('employee.ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->tooltip('Haz clic para copiar')
                    ->copyMessage('CI copiada al portapapeles'),

                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('PYG', locale: 'es_PY')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Advance::getStatusLabel($state))
                    ->color(fn(string $state) => Advance::getStatusColor($state))
                    ->icon(fn(string $state) => Advance::getStatusIcon($state))
                    ->sortable(),

                TextColumn::make('approved_at')
                    ->label('Aprobado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('approvedBy.name')
                    ->label('Aprobado por')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Editado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Advance::getStatusOptions())
                    ->multiple()
                    ->native(false),

                SelectFilter::make('employee_id')
                    ->label('Empleado')
                    ->relationship('employee', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn($record) => "{$record->full_name} (CI: {$record->ci})")
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->multiple()
                    ->native(false),

                Filter::make('approved_at')
                    ->label('Fecha de Aprobación')
                    ->form([
                        DatePicker::make('from')
                            ->label('Desde')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                        DatePicker::make('until')
                            ->label('Hasta')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),
                    ])
                    ->columns(2)
                    ->query(fn(Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn($q, $date) => $q->whereDate('approved_at', '>=', $date))
                        ->when($data['until'], fn($q, $date) => $q->whereDate('approved_at', '<=', $date))),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn(Advance $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar Adelanto')
                    ->modalDescription(fn(Advance $record) => 'Se aprobará el adelanto de ' . number_format((float) $record->amount, 0, ',', '.') . ' Gs. para ' . $record->employee->full_name . '. Se descontará automáticamente en la próxima liquidación de nómina.')
                    ->modalSubmitActionLabel('Sí, aprobar')
                    ->action(function (Advance $record) {
                        $result = $record->approve(Auth::id());

                        Notification::make()
                            ->title($result['success'] ? 'Adelanto Aprobado' : 'Error')
                            ->body($result['message'])
                            ->{$result['success'] ? 'success' : 'danger'}()
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Rechazar')
                    ->icon('heroicon-o-x-circle')
                    ->color('warning')
                    ->visible(fn(Advance $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading('Rechazar Adelanto')
                    ->modalDescription(fn(Advance $record) => 'Se rechazará el adelanto de ' . number_format((float) $record->amount, 0, ',', '.') . ' Gs. para ' . $record->employee->full_name . '. El adelanto quedará en estado Rechazado.')
                    ->modalSubmitActionLabel('Sí, rechazar')
                    ->form([
                        Textarea::make('reason')
                            ->label('Motivo del rechazo')
                            ->placeholder('Ingrese el motivo...')
                            ->rows(3),
                    ])
                    ->action(function (Advance $record, array $data) {
                        $result = $record->reject($data['reason'] ?? null);

                        Notification::make()
                            ->title($result['success'] ? 'Adelanto Rechazado' : 'Error')
                            ->body($result['message'])
                            ->{$result['success'] ? 'warning' : 'danger'}()
                            ->send();
                    }),


                Action::make('export_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn(Advance $record) => $record->isApproved() || $record->isPaid())
                    ->url(fn(Advance $record) => route('advances.pdf', $record))
                    ->openUrlInNewTab(),

            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('approveBulk')
                        ->label('Aprobar')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Aprobar Adelantos')
                        ->modalDescription('Se aprobarán los adelantos seleccionados que estén en estado Pendiente. Los demás serán ignorados.')
                        ->modalSubmitActionLabel('Sí, aprobar seleccionados')
                        ->action(function (Collection $records) {
                            $approved = 0;
                            $skipped = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if (! $record->isPending()) {
                                    $skipped++;
                                    continue;
                                }

                                $result = $record->approve(Auth::id());
                                $result['success'] ? $approved++ : $failed++;
                            }

                            $body = "Se aprobaron {$approved} adelantos.";
                            if ($skipped > 0) {
                                $body .= " {$skipped} ignorados por no estar en estado Pendiente.";
                            }
                            if ($failed > 0) {
                                $body .= " {$failed} no pudieron aprobarse.";
                            }

                            Notification::make()
                                ->title('Aprobación Completada')
                                ->body($body)
                                ->{($skipped + $failed) > 0 ? 'warning' : 'success'}()
                                ->send()
                                ->persistent();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('rejectBulk')
                        ->label('Rechazar')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Rechazar Adelantos')
                        ->modalDescription('Se rechazarán los adelantos seleccionados que estén en estado Pendiente. Los demás serán ignorados.')
                        ->modalSubmitActionLabel('Sí, rechazar seleccionados')
                        ->form([
                            Textarea::make('reason')
                                ->label('Motivo del rechazo')
                                ->placeholder('Ingrese el motivo...')
                                ->rows(3),
                        ])
                        ->action(function (Collection $records, array $data) {
                            $rejected = 0;
                            $skipped = 0;

                            foreach ($records as $record) {
                                if (! $record->isPending()) {
                                    $skipped++;
                                    continue;
                                }

                                $record->reject($data['reason'] ?? null);
                                $rejected++;
                            }

                            $body = "Se rechazaron {$rejected} adelantos.";
                            if ($skipped > 0) {
                                $body .= " {$skipped} ignorados por no estar en estado Pendiente.";
                            }

                            Notification::make()
                                ->warning()
                                ->title('Rechazo Completado')
                                ->body($body)
                                ->send()
                                ->persistent();
                        })
                        ->deselectRecordsAfterCompletion(),


                ]),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('No se encontraron adelantos')
            ->emptyStateDescription('Verifique que se hayan registrado adelantos o intente ajustar los filtros.')
            ->emptyStateIcon('heroicon-o-currency-dollar');
    }

    /**
     * Define las relaciones del recurso (adelantos no tienen cuotas).
     *
     * @return array<int, string>
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Define las páginas del recurso.
     *
     * @return array<string, mixed>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdvances::route('/'),
            'create' => Pages\CreateAdvance::route('/create'),
            'view' => Pages\ViewAdvance::route('/{record}'),
            'edit' => Pages\EditAdvance::route('/{record}/edit'),
        ];
    }

    /**
     * Badge de navegación con conteo de adelantos pendientes.
     */
    public static function getNavigationBadge(): ?string
    {
        return (string) Advance::getPendingCount() ?: null;
    }

    /**
     * Color del badge de navegación.
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
