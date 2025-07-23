<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use PDF;

class DocumentoController extends Controller
{
    /**
     * Genera un documento PDF de Declaración Jurada consolidada.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function generarDjConsolidada(Request $request)
    {
        // 1. Validar los datos de entrada.
        // MODIFICACIÓN: Se añade la validación para la descripción del gasto proyectado.
        $validator = Validator::make($request->all(), [
            'gastos' => 'required|array|min:1',
            'gastos.*.monto' => 'required|numeric|min:0.01',
            'gastos.*.glosa' => 'required|string|max:1000',
            'gastos.*.gasto_proyectado_descripcion' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Obtener los datos necesarios.
        $datosValidados = $validator->validated();
        $gastos = $datosValidados['gastos'];
        $usuario = Auth::user();
        $fechaActual = now()->isoFormat('D [de] MMMM [de] YYYY'); // Formato: 22 de julio de 2025

        $totalGeneral = collect($gastos)->sum('monto');

        // 3. Preparar los datos para la vista.
        $data = [
            'nombreCompleto' => $usuario->name . ' ' . $usuario->last_name,
            'dni' => $usuario->dni, // Asumiendo que el modelo User tiene un campo 'dni'
            'fecha' => $fechaActual,
            'gastos' => $gastos, // El array de gastos ahora incluye la descripción del gasto proyectado
            'totalGeneral' => $totalGeneral,
        ];

        // 4. Cargar la vista de Blade y generar el PDF.
        $pdf = Pdf::loadView('pdf.dj_consolidada', $data);

        // 5. Devolver el PDF como una descarga en el navegador.
        return $pdf->download('declaracion_jurada_consolidada.pdf');
    }

    public function validarDjConsolidada(Request $request, DjConsolidada $dj)
    {
        // Autorización: solo administradores pueden validar.
        if (!Auth::user()->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            abort(403, 'No autorizado.');
        }

        DB::transaction(function () use ($dj) {
            // Actualiza el estado de TODOS los gastos asociados a esta DJ.
            $dj->gastos()->where('estado', 'Pendiente de Validación DJ')->update([
                'estado' => 'Pendiente de Validación Contable'
            ]);

            // Opcional: Marcar la DJ como validada si tienes un campo para ello.
            // $dj->update(['validada' => true]);
        });

        return response()->json(['message' => 'El grupo de gastos asociados a la DJ ha sido validado y ahora pueden ser contabilizados.']);
    }
}
