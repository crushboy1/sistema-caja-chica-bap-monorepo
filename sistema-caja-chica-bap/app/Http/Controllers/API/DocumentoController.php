<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Gasto;
use App\Models\GastoProyectado;
use App\Models\User;
use PDF;

class DocumentoController extends Controller
{

    public function generarDjNuevos(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // COMENTARIO BAP: La validación ahora permite 1 o más gastos.
            'gastos' => 'required|array|min:1',
            'gastos.*.monto_total' => 'required|numeric|min:0.01',
            'gastos.*.glosa' => 'required|string|max:1000',
            'gastos.*.id_gasto_proyectado' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Datos de gastos inválidos.', 'errors' => $validator->errors()], 422);
        }

        $user = Auth::user();
        $gastosData = collect($validator->validated()['gastos']);

        // Enriquecer los datos con la descripción del gasto proyectado para el PDF
        $gastosData = $gastosData->map(function ($gasto) {
            $gastoProyectado = GastoProyectado::find($gasto['id_gasto_proyectado']);
            return array_merge($gasto, [
                'gasto_proyectado_descripcion' => $gastoProyectado->descripcion ?? 'N/A',
            ]);
        });

        $data = [
            'nombreCompleto' => $user->name . ' ' . $user->last_name,
            'dni' => $user->dni,
            'fecha' => now()->isoFormat('D [de] MMMM [de] YYYY'),
            'gastos' => $gastosData,
            'totalGeneral' => $gastosData->sum('monto_total'),
        ];

        $pdf = PDF::loadView('pdf.dj_consolidada', $data);
        return $pdf->download('DJ_Plantilla_Nuevos_' . now()->format('Ymd_His') . '.pdf');
    }
    /**
     * Genera un documento PDF de Declaración Jurada consolidada.
     * Recibe los datos de los gastos directamente del frontend (aún no persistidos).
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function generarDjConsolidada(Request $request)
    {
        // 1. Validar que se reciba un array de IDs de gastos existentes.
        $validated = $request->validate([
            'gastos_ids' => 'required|array|min:1',
            'gastos_ids.*' => 'required|integer|exists:gastos,id',
        ], [
            'gastos_ids.required' => 'Debe proporcionar una lista de IDs de gastos.',
            'gastos_ids.*.exists' => 'Uno de los gastos seleccionados no es válido o no se encontró en la base de datos.',
        ]);

        $user = Auth::user();
        $gastosIds = $validated['gastos_ids'];

        // 2. Buscar los gastos en la base de datos usando los IDs validados.
        // Se cargan las relaciones necesarias para el PDF para optimizar las consultas.
        $gastos = Gasto::with(['gastoProyectado.cuentaContable'])
            ->whereIn('id', $gastosIds)
            ->where('id_registrador', $user->id) // Medida de seguridad: solo gastos del usuario actual.
            ->get();

        // Verificación de seguridad adicional.
        if (count($gastos) !== count($gastosIds)) {
            return response()->json(['message' => 'No tienes permiso para acceder a uno o más de los gastos seleccionados.'], 403);
        }

        // 3. Preparar los datos para la vista del PDF.
        $gastosData = $gastos->map(function ($gasto) {
            return [
                'gasto_proyectado_descripcion' => $gasto->gastoProyectado->descripcion ?? 'N/A',
                'glosa' => $gasto->glosa,
                'monto_total' => $gasto->monto_total,

            ];
        });

        $data = [
            'nombreCompleto' => $user->name . ' ' . $user->last_name,
            'dni' => $user->dni,
            'fecha' => now()->isoFormat('D [de] MMMM [de] YYYY'),
            'gastos' => $gastosData,
            'totalGeneral' => $gastosData->sum('monto_total'),
        ];

        // 4. Cargar la vista de Blade y generar el PDF.
        $pdf = PDF::loadView('pdf.dj_consolidada', $data);

        // 5. Devolver el PDF para ser descargado o mostrado.
        return $pdf->download('DJ_Consolidada_Reconsolidacion_' . now()->format('Ymd_His') . '.pdf');
    }
}
