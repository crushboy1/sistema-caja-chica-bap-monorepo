<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Models\DjConsolidada;
use App\Models\Gasto;
use PDF;
use App\Traits\RegistersHistory;

class DocumentoController extends Controller
{
    use RegistersHistory; // Usar el Trait para acceder a registrarHistorial

    /**
     * Genera un documento PDF de Declaración Jurada consolidada.
     *
     * Este método se mantiene funcionalmente igual, su propósito es generar el PDF para
     * previsualización o descarga, no la persistencia de la DJ en la base de datos ni la vinculación de gastos.
     * Esa lógica se maneja en 'consolidarGastosEnDj'.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function generarDjConsolidada(Request $request)
    {
        // 1. Validar que se reciba un array de IDs de gastos.
        $validated = $request->validate([
            'gastos_ids' => 'required|array|min:1',
            'gastos_ids.*' => 'required|integer|exists:gastos,id',
        ], [
            'gastos_ids.required' => 'Debe proporcionar una lista de gastos para generar el documento.',
            'gastos_ids.*.exists' => 'Uno de los gastos seleccionados no es válido.',
        ]);

        $user = Auth::user();
        $gastosIds = $validated['gastos_ids'];

        // 2. Obtener los gastos desde la base de datos para asegurar la integridad de los datos.
        // Se utiliza el Query Scope que definimos en el modelo Gasto para aplicar la lógica de negocio.
        $gastos = Gasto::with('gastoProyectado')
            ->whereIn('id', $gastosIds)
            ->where('id_registrador', $user->id) // Seguridad: Solo gastos del usuario actual.
            ->where('es_declaracion_jurada', true) // Solo gastos que son del tipo DJ.
            ->get();

        // Si la cantidad de gastos encontrados no coincide con la solicitada, es un error.
        if (count($gastos) !== count($gastosIds)) {
            return response()->json(['message' => 'No tienes permiso o algunos gastos no son válidos para ser incluidos en la DJ.'], 403);
        }

        // 3. Preparar los datos para la vista del PDF.
        $data = [
            'nombreCompleto' => $user->name . ' ' . $user->last_name,
            'dni' => $user->dni,
            'fecha' => now()->isoFormat('D [de] MMMM [de] YYYY'),
            'gastos' => $gastos, // Pasamos la colección de modelos Eloquent a la vista.
            'totalGeneral' => $gastos->sum('monto_total'),
        ];

        // 4. Cargar la vista de Blade y generar el PDF.
        $pdf = Pdf::loadView('pdf.dj_consolidada', $data);

        // 5. Devolver el PDF para ser descargado o mostrado.
        return $pdf->download('DJ_Consolidada_' . now()->format('Ymd_His') . '.pdf');
    }
}
