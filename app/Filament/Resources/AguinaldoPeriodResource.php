<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AguinaldoPeriodResource\Pages;
use App\Filament\Resources\AguinaldoPeriodResource\RelationManagers\AguinaldosRelationManager;
use App\Models\AguinaldoPeriod;
use App\Models\Company;
use App\Services\AguinaldoService;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AguinaldoPeriodResource extends Resource
{
    protected static ?string $model = AguinaldoPeriod::class;
    protected static ?string $navigationGroup = 'Nóminas';
    protected static ?string $navigationLabel = 'Aguinaldos';
    protected static ?string $label = 'Período de Aguinaldo';
    protected static ?string $pluralLabel = 'Períodos de Aguinaldo';
    protected static ?string $slug = 'aguinaldo-periodos';
    protected static ?string $navigationIcon = 'heroicon-o-gift';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información del Período')
                    ->schema([
                        Select::make('company_id')
                            ->label('Empresa')
                            ->relationship('company', 'name', fn($query) => $query->active())
                            ->getOptionLabelFromRecordUsing(fn(Company $record) =>
                                $record->name . ($record->trade_name ? ' (' . $record->trade_name . ')' : '')
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->columnSpan(1),

                        TextInput::make('year')
                            ->label('Año')
                            ->numeric()
                            ->minValue(2000)
                            ->maxValue(2100)
                            ->default(now()->year)
                            ->required()
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
                            ->placeholder('Observaciones o comentarios sobre este período de aguinaldo')
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
                TextColumn::make('company.name')
                    ->label('Empresa')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-building-office')
                    ->iconColor('primary'),

                TextColumn::make('year')
                    ->label('Año')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('aguinaldos_count')
                    ->label('Aguinaldos')
                    ->counts('aguinaldos')
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
                SelectFilter::make('company_id')
                    ->label('Empresa')
                    ->relationship('company', 'name', fn($query) => $query->active())
                    ->getOptionLabelFromRecordUsing(fn(Company $record) =>
                        $record->name . ($record->trade_name ? ' (' . $record->trade_name . ')' : '')
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft'      => 'Borrador',
                        'processing' => 'En Proceso',
                        'closed'     => 'Cerrado',
                    ])
                    ->native(false),

                SelectFilter::make('year')
                    ->label('Año')
                    ->options(function () {
                        $years = [];
                        for ($y = now()->year + 1; $y >= now()->year - 5; $y--) {
                            $years[$y] = $y;
                        }
                        return $years;
                    })
                    ->native(false),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('generate_aguinaldos')
                    ->label('Generar Aguinaldos')
                    ->icon('heroicon-o-document-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Generar Aguinaldos')
                    ->modalDescription(
                        fn(AguinaldoPeriod $record) =>
                        "¿Está seguro de generar los aguinaldos para {$record->company->name} - {$record->year}? " .
                            "Esta acción creará aguinaldos para todos los empleados que hayan tenido nóminas en el año."
                    )
                    ->action(function (AguinaldoPeriod $record, AguinaldoService $aguinaldoService) {
                        $count = $aguinaldoService->generateForPeriod($record);

                        if ($count > 0) {
                            $record->update([
                                'status' => 'processing',
                            ]);

                            Notification::make()
                                ->success()
                                ->title('Aguinaldos generados')
                                ->body("Se generaron exitosamente {$count} aguinaldos.")
                                ->send();
                        } else {
                            Notification::make()
                                ->warning()
                                ->title('No se generaron aguinaldos')
                                ->body('Es posible que ya hayan sido generados o que no haya nóminas para el año seleccionado.')
                                ->send();
                        }
                    })
                    ->visible(fn(AguinaldoPeriod $record) => $record->status === 'draft'),

                Action::make('close_period')
                    ->label('Cerrar Período')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cerrar Período de Aguinaldo')
                    ->modalDescription(
                        fn(AguinaldoPeriod $record) =>
                        "¿Está seguro de cerrar el período de aguinaldo {$record->company->name} - {$record->year}? " .
                            "Una vez cerrado, no se podrán generar más aguinaldos para este período."
                    )
                    ->action(function (AguinaldoPeriod $record) {
                        $record->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                        ]);

                        Notification::make()
                            ->success()
                            ->title('Período cerrado')
                            ->body("El período de aguinaldo {$record->year} ha sido cerrado exitosamente.")
                            ->send();
                    })
                    ->visible(fn(AguinaldoPeriod $record) => $record->status === 'processing'),

                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn(AguinaldoPeriod $record) => $record->status === 'draft'),
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
            ->defaultSort('year', 'desc')
            ->emptyStateHeading('No hay períodos de aguinaldo registrados')
            ->emptyStateDescription('Comienza creando un período de aguinaldo para generar los recibos del 13° salario.')
            ->emptyStateIcon('heroicon-o-gift');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Información del Período')
                    ->schema([
                        Group::make([
                            TextEntry::make('company.name')
                                ->label('Empresa')
                                ->icon('heroicon-o-building-office'),

                            TextEntry::make('year')
                                ->label('Año')
                                ->badge()
                                ->color('info'),
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

                InfolistSection::make('Resumen')
                    ->schema([
                        Group::make([
                            TextEntry::make('aguinaldos_count')
                                ->label('Total de Aguinaldos')
                                ->state(fn(AguinaldoPeriod $record) => $record->aguinaldos()->count())
                                ->badge()
                                ->color('success'),

                            TextEntry::make('total_amount')
                                ->label('Monto Total')
                                ->state(fn(AguinaldoPeriod $record) => 'Gs. ' . number_format($record->aguinaldos()->sum('aguinaldo_amount'), 0, ',', '.'))
                                ->weight('bold')
                                ->color('success'),
                        ])->columns(2),
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
            AguinaldosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAguinaldoPeriods::route('/'),
            'create' => Pages\CreateAguinaldoPeriod::route('/create'),
            'view' => Pages\ViewAguinaldoPeriod::route('/{record}'),
            'edit' => Pages\EditAguinaldoPeriod::route('/{record}/edit'),
        ];
    }
}
