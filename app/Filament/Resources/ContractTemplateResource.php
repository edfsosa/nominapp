<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractTemplateResource\Pages;
use App\Models\Contract;
use App\Models\ContractTemplate;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

/** Recurso para gestionar las plantillas de cuerpo/cláusulas por tipo de contrato. */
class ContractTemplateResource extends Resource
{
    protected static ?string $model = ContractTemplate::class;

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationLabel = 'Plantillas de Contratos';

    protected static ?string $modelLabel = 'Plantilla de Contrato';

    protected static ?string $pluralModelLabel = 'Plantillas de Contratos';

    protected static ?string $slug = 'plantillas-contratos';

    protected static ?int $navigationSort = 50;

    /** Botones comunes del RichEditor para todas las secciones. */
    private static function richEditorToolbar(): array
    {
        return ['bold', 'italic', 'underline', 'orderedList', 'bulletList', 'redo', 'undo'];
    }

    /** Componente de referencia de variables reutilizable. */
    private static function variablesReference(): Placeholder
    {
        $vars = ContractTemplate::getAvailableVariables();

        $items = collect($vars)
            ->map(fn ($desc, $token) => '<code style="background:#f3f4f6;padding:1px 4px;border-radius:3px;font-size:11px;">'
                .e($token).'</code> — '
                .e($desc))
            ->implode('<br>');

        return Placeholder::make('variables_reference')
            ->label('Variables disponibles')
            ->content(new HtmlString('<div style="line-height:1.8;">'.$items.'</div>'))
            ->columnSpanFull();
    }

    /**
     * Define el formulario de edición de la plantilla con 4 tabs por sección.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Secciones del contrato')
                    ->tabs([
                        Tabs\Tab::make('Párrafo Introductorio')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                self::variablesReference(),

                                Actions::make([
                                    FormAction::make('load_default_intro')
                                        ->label('Cargar texto base')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->color('gray')
                                        ->action(fn (Set $set) => $set('intro_text', ContractTemplate::getDefaultIntroText())),
                                ])->columnSpanFull(),

                                RichEditor::make('intro_text')
                                    ->label('Texto del párrafo introductorio')
                                    ->helperText('Si se deja vacío, el PDF usará el párrafo introductorio estándar hardcodeado. Use las variables de arriba para insertar datos dinámicos.')
                                    ->toolbarButtons(self::richEditorToolbar())
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Cuerpo / Cláusulas')
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                self::variablesReference(),

                                Actions::make([
                                    FormAction::make('load_default_body')
                                        ->label('Cargar texto base')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->color('gray')
                                        ->action(fn (Set $set) => $set('body', ContractTemplate::getDefaultBodyText())),
                                ])->columnSpanFull(),

                                RichEditor::make('body')
                                    ->label('Cuerpo / Cláusulas')
                                    ->helperText('Este contenido se pre-rellena automáticamente al crear un contrato con este tipo. Puede personalizarse por contrato.')
                                    ->toolbarButtons(self::richEditorToolbar())
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Texto de Cierre')
                            ->icon('heroicon-o-check-circle')
                            ->schema([
                                self::variablesReference(),

                                Actions::make([
                                    FormAction::make('load_default_closing')
                                        ->label('Cargar texto base')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->color('gray')
                                        ->action(fn (Set $set) => $set('closing_text', ContractTemplate::getDefaultClosingText())),
                                ])->columnSpanFull(),

                                RichEditor::make('closing_text')
                                    ->label('Texto de cierre')
                                    ->helperText('Se muestra después de las cláusulas y antes de las firmas (ej: declaraciones finales, lugar y fecha de suscripción).')
                                    ->toolbarButtons(self::richEditorToolbar())
                                    ->columnSpanFull(),
                            ]),

                        Tabs\Tab::make('Notas en Firmas')
                            ->icon('heroicon-o-pencil')
                            ->schema([
                                Placeholder::make('signature_notes_help')
                                    ->label('')
                                    ->content('Texto breve que se muestra bajo las líneas de firma del empleado y el empleador (ej: aclaraciones de cargo, número de ejemplares, etc.).')
                                    ->columnSpanFull(),

                                Actions::make([
                                    FormAction::make('load_default_signature_notes')
                                        ->label('Cargar texto base')
                                        ->icon('heroicon-o-arrow-down-tray')
                                        ->color('gray')
                                        ->action(fn (Set $set) => $set('signature_notes', ContractTemplate::getDefaultSignatureNotes())),
                                ])->columnSpanFull(),

                                Textarea::make('signature_notes')
                                    ->label('Notas en la sección de firmas')
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Define la tabla de listado de plantillas.
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo de Contrato')
                    ->formatStateUsing(fn ($state) => $state ? Contract::getTypeLabel($state) : '—')
                    ->badge()
                    ->color(fn ($state) => $state ? Contract::getTypeColor($state) : 'gray')
                    ->icon(fn ($state) => $state ? Contract::getTypeIcon($state) : null),

                TextColumn::make('sections_configured')
                    ->label('Secciones configuradas')
                    ->getStateUsing(function (ContractTemplate $record): string {
                        $sections = collect([
                            $record->intro_text ? 'Intro' : null,
                            $record->body ? 'Cláusulas' : null,
                            $record->closing_text ? 'Cierre' : null,
                            $record->signature_notes ? 'Firmas' : null,
                        ])->filter()->implode(', ');

                        return $sections ?: 'Sin personalizar';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Sin personalizar' ? 'gray' : 'success'),

                TextColumn::make('body')
                    ->label('Vista previa (cláusulas)')
                    ->html()
                    ->limit(100)
                    ->placeholder('Sin cláusulas'),

                TextColumn::make('updated_at')
                    ->label('Última modificación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make()
                    ->label('Editar plantilla')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary'),
            ])
            ->paginated(false);
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractTemplates::route('/'),
            'edit' => Pages\EditContractTemplate::route('/{record}/edit'),
        ];
    }
}
