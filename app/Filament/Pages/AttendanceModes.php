<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Página informativa sobre los modos de marcación disponibles y sus URLs. */
class AttendanceModes extends Page
{
    protected static ?string $navigationLabel = 'Modos de Marcación';
    protected static ?string $navigationIcon  = 'heroicon-o-qr-code';
    protected static ?string $navigationGroup = 'Asistencias';
    protected static ?int    $navigationSort  = 10;
    protected static ?string $title           = 'Modos de Marcación';
    protected static string  $view            = 'filament.pages.attendance-modes';

    /**
     * Pasa las URLs y los QR codes SVG de cada modo a la vista.
     *
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $mobileUrl   = route('mark.show');
        $terminalUrl = route('terminal.show');

        return [
            'mobileUrl'   => $mobileUrl,
            'terminalUrl' => $terminalUrl,
            'mobileQr'    => (string) QrCode::format('svg')->size(150)->margin(1)->generate($mobileUrl),
            'terminalQr'  => (string) QrCode::format('svg')->size(150)->margin(1)->generate($terminalUrl),
        ];
    }
}
