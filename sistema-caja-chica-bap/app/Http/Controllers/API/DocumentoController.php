<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator; // Asegúrate de que esta línea esté presente
use Illuminate\Support\Facades\DB;
use App\Models\DjConsolidada;
use App\Models\Gasto;
use App\Models\GastoProyectado; // Asegúrate de importar GastoProyectado
use App\Models\CuentaContable; // Asegúrate de importar CuentaContable
use PDF; // Asegúrate de que esta línea esté presente
use App\Traits\RegistersHistory;

class DocumentoController extends Controller
{
    use RegistersHistory;

    /**
     * Genera un documento PDF de Declaración Jurada consolidada.
     * Recibe los datos de los gastos directamente del frontend (aún no persistidos).
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function generarDjConsolidada(Request $request)
    {
        // 1. Validar que se reciba un array de datos de gastos.
        // Los gastos no están persistidos aún, por lo que NO se valida 'exists:gastos,id'.
        // Se validan los campos mínimos necesarios para la plantilla.
        $validator = Validator::make($request->all(), [
            'gastos' => 'required|array|min:1',
            'gastos.*.id_gasto_proyectado' => 'required|integer', // No 'exists' aquí
            'gastos.*.fecha_documento' => 'required|date|before_or_equal:today', // Añadido before_or_equal
            'gastos.*.monto_total' => 'required|numeric|min:0.01',
            'gastos.*.glosa' => 'required|string|max:1000',
            'gastos.*.tipo_documento' => 'required|string|max:100', // Añadido max
            'gastos.*.serie_documento' => 'nullable|string|max:20', // Nullable para DJ
            'gastos.*.correlativo_documento' => 'nullable|string|max:50', // Nullable para DJ
            'gastos.*.es_declaracion_jurada' => 'required|boolean', // Confirmar que es booleano
            'gastos.*.moneda' => 'sometimes|in:PEN,USD', // Opcional
            'id_fondo_efectivo' => 'required|integer|exists:fondo_efectivo,id_fondo',
            'id_registrador' => 'required|integer|exists:users,id',
        ], [
            'gastos.required' => 'Debe proporcionar una lista de gastos para generar el documento.',
            'gastos.min' => 'Debe proporcionar al menos un gasto para generar el documento.',
            'gastos.*.id_gasto_proyectado.required' => 'El gasto proyectado es obligatorio para cada gasto.',
            'gastos.*.fecha_documento.required' => 'La fecha del documento es obligatoria para cada gasto.',
            'gastos.*.fecha_documento.date' => 'La fecha del documento no es una fecha válida.',
            'gastos.*.fecha_documento.before_or_equal' => 'La fecha del documento no puede ser una fecha futura.',
            'gastos.*.monto_total.required' => 'El monto total es obligatorio para cada gasto.',
            'gastos.*.monto_total.numeric' => 'El monto total debe ser un número.',
            'gastos.*.monto_total.min' => 'El monto total debe ser mayor que cero.',
            'gastos.*.glosa.required' => 'La glosa es obligatoria para cada gasto.',
            'gastos.*.glosa.max' => 'La glosa no puede exceder los 1000 caracteres.',
            'gastos.*.tipo_documento.required' => 'El tipo de documento es obligatorio para cada gasto.',
            'gastos.*.es_declaracion_jurada.required' => 'El campo "es declaración jurada" es obligatorio.',
            'id_fondo_efectivo.required' => 'El fondo de efectivo es obligatorio.',
            'id_fondo_efectivo.exists' => 'El fondo de efectivo seleccionado no es válido.',
            'id_registrador.required' => 'El ID del registrador es obligatorio.',
            'id_registrador.exists' => 'El registrador no es válido.',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Error de validación.', 'errors' => $validator->errors()], 422);
        }

        $validatedData = $validator->validated();
        $user = Auth::user(); // O usar $user = \App\Models\User::find($validatedData['id_registrador']); si el registrador no es siempre el autenticado.

        // 2. Obtener datos adicionales para la vista del PDF.
        $gastosData = collect($validatedData['gastos'])->map(function ($gasto) {
            // Cargar la descripción del gasto proyectado y la cuenta contable si es necesario para el PDF
            $gastoProyectado = GastoProyectado::find($gasto['id_gasto_proyectado']);
            $cuentaContable = $gastoProyectado ? CuentaContable::find($gastoProyectado->id_cuenta_contable) : null;

            return array_merge($gasto, [
                'gasto_proyectado_descripcion' => $gastoProyectado->descripcion ?? 'N/A',
                'cuenta_contable_info' => $cuentaContable ? "{$cuentaContable->codigo_cuenta} - {$cuentaContable->descripcion}" : 'N/A',
            ]);
        });

        // 3. Preparar los datos para la vista del PDF.
        $data = [
            'nombreCompleto' => $user->name . ' ' . $user->last_name,
            'dni' => $user->dni,
            'fecha' => now()->isoFormat('D [de] MMMM [de] YYYY'),
            'gastos' => $gastosData, // Pasamos los datos procesados
            'totalGeneral' => $gastosData->sum('monto_total'),
        ];

        // 4. Cargar la vista de Blade y generar el PDF.
        $pdf = PDF::loadView('pdf.dj_consolidada', $data);

        // 5. Devolver el PDF para ser descargado o mostrado.
        return $pdf->download('DJ_Consolidada_Previa_' . now()->format('Ymd_His') . '.pdf');
    }
}