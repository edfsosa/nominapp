<?php

namespace App\Http\Controllers;

use App\Models\Aguinaldo;
use Illuminate\Support\Facades\Storage;

class AguinaldoController extends Controller
{
    /**
     * Permite descargar el recibo de aguinaldo en formato PDF. Verifica que el archivo exista antes de intentar descargarlo, y retorna un error 404 si no se encuentra.
     *
     * @param Aguinaldo $aguinaldo
     * @return void
     */
    public function download(Aguinaldo $aguinaldo)
    {
        if (! $aguinaldo->pdf_path || ! Storage::disk('public')->exists($aguinaldo->pdf_path)) {
            abort(404, 'El archivo PDF no existe.');
        }

        return response()->file(Storage::disk('public')->path($aguinaldo->pdf_path), [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
