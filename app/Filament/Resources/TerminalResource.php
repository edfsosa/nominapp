<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerminalResource\Pages;
use App\Models\Branch;
use App\Models\Terminal;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid as InfoGrid;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Gestión de terminales físicas de marcación de asistencia. */
class TerminalResource extends Resource
{
    protected static ?string $model = Terminal::class;
    protected static ?string $navigationLabel = 'Terminales';
    protected static ?string $label = 'terminal';
    protected static ?string $pluralLabel = 'terminales';
    protected static ?string $slug = 'terminales';
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $navigationGroup = 'Asistencias';
    protected static ?int $navigationSort = 6;

    /**
     * Formulario de creación y edición de terminales.
     *
     * @param  Form $form
     * @return Form
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Identificación')
                    ->icon('heroicon-o-computer-desktop')
                    ->compact()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->placeholder('Ej: Terminal Entrada Principal')
                            ->required()
                            ->maxLength(100),

                        Select::make('branch_id')
                            ->label('Sucursal')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),

                        Select::make('status')
                            ->label('Estado')
                            ->options(Terminal::getStatusOptions())
                            ->native(false)
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(3),

                Section::make('Dispositivo')
                    ->icon('heroicon-o-device-tablet')
                    ->compact()
                    ->schema([
                        TextInput::make('device_brand')
                            ->label('Marca')
                            ->placeholder('Ej: Samsung, Apple, Lenovo')
                            ->maxLength(60),

                        TextInput::make('device_model')
                            ->label('Modelo')
                            ->placeholder('Ej: Galaxy Tab A8')
                            ->maxLength(100),

                        TextInput::make('device_serial')
                            ->label('Número de Serie')
                            ->maxLength(100),

                        TextInput::make('device_mac')
                            ->label('Dirección MAC')
                            ->placeholder('AA:BB:CC:DD:EE:FF')
                            ->maxLength(17)
                            ->regex('/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/')
                            ->validationMessages(['regex' => 'Ingrese una dirección MAC válida. Ej: AA:BB:CC:DD:EE:FF']),

                        Textarea::make('device_notes')
                            ->label('Notas del dispositivo')
                            ->placeholder('Ej: Pantalla con rayón en esquina superior derecha')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Instalación')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->compact()
                    ->schema([
                        DatePicker::make('installed_at')
                            ->label('Fecha de instalación')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->closeOnDateSelection(),

                        Select::make('installed_by_id')
                            ->label('Instalado por')
                            ->relationship('installedBy', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->default(fn() => Auth::id()),
                    ])
                    ->columns(2),
            ]);
    }

    /**
     * Infolist de visualización de la terminal con QR de acceso.
     *
     * @param  Infolist $infolist
     * @return Infolist
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoSection::make('Identificación')
                    ->schema([
                        InfoGrid::make(3)->schema([
                            TextEntry::make('name')
                                ->label('Nombre')
                                ->icon('heroicon-o-computer-desktop'),

                            TextEntry::make('branch.name')
                                ->label('Sucursal')
                                ->icon('heroicon-o-building-storefront')
                                ->badge()
                                ->color('info'),

                            TextEntry::make('status')
                                ->label('Estado')
                                ->formatStateUsing(fn(string $state) => Terminal::getStatusLabels()[$state] ?? $state)
                                ->color(fn(string $state) => Terminal::getStatusColors()[$state] ?? 'gray')
                                ->badge(),
                        ]),

                        InfoGrid::make(2)->schema([
                            TextEntry::make('code')
                                ->label('Código de terminal')
                                ->icon('heroicon-o-key')
                                ->badge()
                                ->color('gray')
                                ->copyable()
                                ->copyMessage('Código copiado'),

                            TextEntry::make('url')
                                ->label('URL de acceso')
                                ->icon('heroicon-o-link')
                                ->copyable()
                                ->copyMessage('URL copiada')
                                ->state(fn(Terminal $record) => $record->url),
                        ]),

                        TextEntry::make('qr_code')
                            ->label('QR de acceso')
                            ->html()
                            ->state(fn(Terminal $record) => '<div style="display:inline-block;background:#fff;padding:12px;border-radius:8px;border:1px solid #e5e7eb">'
                                . QrCode::size(180)->generate($record->url)
                                . '</div>'
                            ),
                    ]),

                InfoSection::make('Dispositivo')
                    ->schema([
                        InfoGrid::make(3)->schema([
                            TextEntry::make('device_brand')
                                ->label('Marca')
                                ->placeholder('-'),

                            TextEntry::make('device_model')
                                ->label('Modelo')
                                ->placeholder('-'),

                            TextEntry::make('device_serial')
                                ->label('Número de Serie')
                                ->copyable()
                                ->placeholder('-'),
                        ]),

                        InfoGrid::make(2)->schema([
                            TextEntry::make('device_mac')
                                ->label('Dirección MAC')
                                ->copyable()
                                ->placeholder('-'),

                            TextEntry::make('last_seen_at')
                                ->label('Última actividad')
                                ->dateTime('d/m/Y H:i')
                                ->placeholder('Sin actividad registrada')
                                ->since(),
                        ]),

                        TextEntry::make('device_notes')
                            ->label('Notas')
                            ->placeholder('Sin notas')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn(Terminal $record) => $record->device_brand || $record->device_model || $record->device_serial || $record->device_mac || $record->device_notes),

                InfoSection::make('Instalación')
                    ->schema([
                        InfoGrid::make(2)->schema([
                            TextEntry::make('installed_at')
                                ->label('Fecha de instalación')
                                ->date('d/m/Y')
                                ->placeholder('-'),

                            TextEntry::make('installedBy.name')
                                ->label('Instalado por')
                                ->placeholder('-'),
                        ]),
                    ])
                    ->visible(fn(Terminal $record) => $record->installed_at || $record->installed_by_id),
            ]);
    }

    /**
     * Tabla de terminales con columnas, filtros y acciones de ciclo de vida.
     *
     * @param  Table $table
     * @return Table
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-building-storefront')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('device_description')
                    ->label('Dispositivo')
                    ->placeholder('Sin datos')
                    ->toggleable(),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn(string $state) => Terminal::getStatusLabels()[$state] ?? $state)
                    ->color(fn(string $state) => Terminal::getStatusColors()[$state] ?? 'gray')
                    ->sortable(),

                TextColumn::make('last_seen_at')
                    ->label('Última actividad')
                    ->since()
                    ->placeholder('Sin actividad')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('installed_at')
                    ->label('Instalada')
                    ->date('d/m/Y')
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(Terminal::getStatusOptions())
                    ->native(false),
            ])
            ->actions([
                Action::make('activate')
                    ->label('Activar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(Terminal $record) => $record->isInactive())
                    ->requiresConfirmation()
                    ->modalHeading('Activar terminal')
                    ->modalDescription(fn(Terminal $record) => "La terminal \"{$record->name}\" volverá a estar disponible para marcaciones.")
                    ->modalSubmitActionLabel('Sí, activar')
                    ->action(function (Terminal $record) {
                        $record->update(['status' => 'active']);
                        Notification::make()->success()->title('Terminal activada')->send();
                    }),

                Action::make('deactivate')
                    ->label('Desactivar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(Terminal $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Desactivar terminal')
                    ->modalDescription(fn(Terminal $record) => "La terminal \"{$record->name}\" dejará de aceptar marcaciones y mostrará una pantalla de fuera de servicio.")
                    ->modalSubmitActionLabel('Sí, desactivar')
                    ->action(function (Terminal $record) {
                        $record->update(['status' => 'inactive']);
                        Notification::make()->warning()->title('Terminal desactivada')->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No hay terminales registradas')
            ->emptyStateDescription('Crea una terminal y configurá el dispositivo físico con su URL de acceso.')
            ->emptyStateIcon('heroicon-o-computer-desktop');
    }

    /**
     * Relaciones del recurso.
     *
     * @return array
     */
    public static function getRelations(): array
    {
        return [];
    }

    /**
     * Páginas del recurso.
     *
     * @return array
     */
    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTerminals::route('/'),
            'create' => Pages\CreateTerminal::route('/create'),
            'view'   => Pages\ViewTerminal::route('/{record}'),
            'edit'   => Pages\EditTerminal::route('/{record}/edit'),
        ];
    }

    /**
     * Badge de navegación: muestra el conteo de terminales inactivas.
     *
     * @return string|null
     */
    public static function getNavigationBadge(): ?string
    {
        $inactive = Terminal::where('status', 'inactive')->count();
        return $inactive > 0 ? (string) $inactive : null;
    }

    /**
     * Color del badge de navegación.
     *
     * @return string|null
     */
    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }
}
