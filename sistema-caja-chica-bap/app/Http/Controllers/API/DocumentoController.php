<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PDF;

class DocumentoController extends Controller
{
    /**
     * Genera un PDF de Declaración Jurada con los datos proporcionados.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generarDJ(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'monto' => 'required|numeric',
            'glosa' => 'required|string',
        ]);

        $pdfData = [
            'nombre' => $user->name . ' ' . $user->last_name,
            'dni' => $user->numero_documento_identidad, // Asumiendo que tienes este campo en tu modelo User
            'monto' => number_format($data['monto'], 2, '.', ','),
            'glosa' => $data['glosa'],
        ];

        // Carga la vista de Blade y le pasa los datos
        $pdf = PDF::loadView('pdf.declaracion_jurada', $pdfData);

        // Devuelve el PDF para que el navegador lo descargue
        return $pdf->download('Declaracion-Jurada-' . $user->id . '-' . time() . '.pdf');
    }
}