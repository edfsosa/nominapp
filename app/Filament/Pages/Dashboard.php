<?php

namespace App\Filament\Pages;

/** Dashboard personalizado con grid de 4 columnas para soporte de widgets half-width a futuro. */
class Dashboard extends \Filament\Pages\Dashboard
{
    /**
     * Usa 4 columnas para que en el futuro se puedan colocar widgets de 2 cols lado a lado.
     * Los widgets con $columnSpan = 'full' ocupan las 4 columnas (ancho completo).
     */
    public function getColumns(): int|string|array
    {
        return 4;
    }
}
