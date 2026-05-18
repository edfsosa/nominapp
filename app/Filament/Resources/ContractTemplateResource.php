<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractTemplateResource\Pages;
use App\Models\Contract;
use App\Models\ContractTemplate;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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

    /**
     * Define el formulario de edición de la plantilla.
     */
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                RichEditor::make('body')
                    ->label('Cuerpo / Cláusulas')
                    ->helperText('Este contenido se pre-rellena automáticamente al crear un contrato con este tipo.')
                    ->columnSpanFull()
                    ->toolbarButtons([
                        'bold',
                        'italic',
                        'underline',
                        'orderedList',
                        'bulletList',
                        'redo',
                        'undo',
                    ]),
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
                    ->formatStateUsing(fn ($state) => Contract::getTypeLabel($state))
                    ->badge()
                    ->color(fn ($state) => Contract::getTypeColor($state))
                    ->icon(fn ($state) => Contract::getTypeIcon($state)),

                TextColumn::make('body')
                    ->label('Vista previa')
                    ->html()
                    ->limit(120)
                    ->placeholder('Sin plantilla'),

                TextColumn::make('updated_at')
                    ->label('Última modificación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make()->label('Editar plantilla'),
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
