<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Descarga de archivos de log de la aplicación. */
class LogController extends Controller
{
    /**
     * Descarga un archivo de log específico.
     *
     * El parámetro `file` debe ser un nombre de archivo dentro de `storage/logs/`
     * y terminar en `.log`. Cualquier intento de path traversal es rechazado.
     */
    public function download(Request $request): BinaryFileResponse
    {
        $filename = $request->query('file', '');

        abort_if(
            ! $filename || ! preg_match('/^[\w\-\.]+\.log$/', $filename),
            404
        );

        $path = storage_path('logs/'.$filename);

        abort_unless(file_exists($path), 404);

        return response()->download($path, $filename, [
            'Content-Type' => 'text/plain',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
