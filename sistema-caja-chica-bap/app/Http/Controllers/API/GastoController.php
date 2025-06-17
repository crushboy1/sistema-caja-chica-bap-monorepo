<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Gasto;
use App\Models\FondoEfectivo;
use App\Models\HistorialAprobacionGasto;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * GastoController se encarga de todo el ciclo de vida de las declaraciones de gastos.
 * Esta versión refactorizada utiliza endpoints de acción específicos (approve, observe, etc.)
 * para una API más clara, segura y mantenible, eliminando el "Fat Controller".
 */
class GastoController extends Controller
{
    /**
     * Muestra una lista de gastos.
     * La lógica de autorización determina qué gastos puede ver el usuario según su rol.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = Gasto::with([
            'registrador.role',
            'registrador.area:id,name',
            'jefeAprobador:id,name,last_name',
            'validadorAdm:id,name,last_name',
            'cuentaContable',
            'fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado'
        ]);

        // --- LÓGICA DE VISUALIZACIÓN POR ROL Y SCOPE ---

        // CASO 1: Es un Jefe de Área en su panel de "Aprobaciones"
        if ($request->input('scope') === 'aprobaciones') {

            // --- CAMBIO CLAVE ---
            // Se ha añadido la lógica para el rol 'colaborador' en esta misma vista.

            if ($user->hasRole('jefe_area')) {
                // El JEFE DE ÁREA ve los gastos de su equipo que están 'Pendiente de Aprobación' o que han sido 'Observado' por ADM.
                $query->whereIn('estado', ['Pendiente de Aprobación', 'Observado'])
                    ->where('id_registrador', '!=', $user->id)
                    ->whereHas('registrador', function ($subQ) use ($user) {
                        $subQ->where('area_id', $user->area_id);
                    });
            } elseif ($user->hasRole('colaborador')) {
                // El COLABORADOR ve únicamente sus propios gastos que han sido marcados como 'Observado'.
                $query->where('estado', 'Observado')
                    ->where('id_registrador', $user->id);
            } else {
                // Si otro rol entra a esta vista (ej. ADM), no verá nada por defecto en el scope de "aprobaciones".
                $query->whereRaw('1 = 0'); // Devuelve una consulta vacía.
            }
        }
        // CASO 2: Es cualquier otro rol o vista (Trazabilidad, Auditoría, etc.)
        else {
            if ($user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
                // Admin ve todo
            } elseif ($user->hasRole('jefe_area')) {
                // Jefe de área ve todo lo de su área
                $query->whereHas('registrador', function ($q) use ($user) {
                    $q->where('area_id', $user->area_id);
                });
            } else {
                // Colaborador solo ve lo suyo
                $query->where('id_registrador', $user->id);
            }
        }

        // Aplicar filtros de búsqueda adicionales de la request.
        if ($request->filled('area_id')) {
            $query->whereHas('registrador', function ($q) use ($request) {
                $q->where('area_id', $request->area_id);
            });
        }
        if ($request->filled('estado') && $request->estado !== 'Todos') {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('codigo_gasto')) {
            $query->where('codigo_gasto', 'like', '%' . $request->codigo_gasto . '%');
        }
        if ($request->filled('registrador_name')) {
            $searchTerm = strtolower($request->registrador_name);
            $query->whereHas('registrador', function ($q) use ($searchTerm) {
                $q->where(DB::raw("CONCAT(LOWER(name), ' ', LOWER(last_name))"), 'like', '%' . $searchTerm . '%');
            });
        }
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_documento', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_documento', '<=', $request->fecha_fin);
        }

        $gastos = $query->orderBy('created_at', 'desc')->get();
        return response()->json($gastos);
    }

    /**
     * Paso 1: Almacena un nuevo gasto en la base de datos.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'id_fondo_efectivo' => 'required|exists:fondo_efectivo,id_fondo',
            'fecha_documento' => 'required|date',
            'tipo_documento' => 'required_if:es_declaracion_jurada,false|string|max:100',
            'serie_documento' => 'nullable|string|max:20',
            'correlativo_documento' => 'nullable|string|max:50',
            'monto_total' => 'required|numeric|min:0.01',
            'moneda' => 'required|string|in:PEN,USD',
            'pertenece_proyecto' => 'required|boolean',
            'id_cuenta_contable' => 'required|exists:cuentas_contables,id',
            'glosa' => 'required|string|max:1000',
            'evidencia' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'es_declaracion_jurada' => 'required|boolean',
            'comentario' => 'nullable|string|max:2000',
        ]);

        $user = Auth::user();
        $fondo = FondoEfectivo::findOrFail($validatedData['id_fondo_efectivo']);
        $montoNuevoGasto = $validatedData['monto_total'];
        // --- LÓGICA DE SALDO OPERATIVO (IMPLEMENTACIÓN COMPLETA) ---
        // 1. Obtener la colección de gastos que ya están "en proceso".
        $gastosEnProcesoCollection = $fondo->gastos()
            ->whereIn('estado', ['Pendiente de Aprobación', 'Pendiente de Validación Contable'])
            ->get();
        // 2. Calcular el monto comprometido, convirtiendo los gastos en USD a PEN.
        $montoComprometido = $gastosEnProcesoCollection->reduce(function ($carry, $gasto) {
            if ($gasto->moneda === 'PEN') {
                return $carry + $gasto->monto_total;
            } else { // El gasto es en USD
                // Usar el tipo de cambio referencial si existe, si no, un valor por defecto seguro.
                $tipoCambio = $gasto->tipo_cambio_referencial ?? 3.8; // Valor conservador por defecto
                return $carry + ($gasto->monto_total * $tipoCambio);
            }
        }, 0);
        // 3. Calcular el saldo operativo real.
        $saldoOperativo = $fondo->monto_disponible - $montoComprometido;
        // 4. Convertir el monto del nuevo gasto a PEN si es necesario para la validación.
        $montoNuevoGastoEnPen = $validatedData['moneda'] === 'PEN'
            ? $montoNuevoGasto
            : ($montoNuevoGasto * ($validatedData['tipo_cambio_referencial'] ?? 3.8));
        // 5. Validar el nuevo gasto contra el saldo operativo.
        if ($saldoOperativo < $montoNuevoGastoEnPen) {
            throw ValidationException::withMessages([
                'monto_total' => 'El monto del gasto excede el saldo operativo real del fondo (Aprox. S/. ' . number_format($saldoOperativo, 2) . ').'
            ]);
        }

        return DB::transaction(function () use ($request, $validatedData, $user) {
            $path = $request->file('evidencia')->store('evidencias_gastos', 'public');

            // LÓGICA DE ESTADOS 
            $estadoInicial = $user->hasRole('jefe_area') ? 'Pendiente de Validación Contable' : 'Pendiente de Aprobación';

            // Unir los datos validados con los datos generados por el servidor
            $dataToCreate = array_merge($validatedData, [
                'id_registrador' => $user->id,
                'ruta_evidencia' => $path,
                'estado' => $estadoInicial,
                'tipo_documento' => $validatedData['es_declaracion_jurada'] ? 'Declaración Jurada' : $validatedData['tipo_documento'],
                'serie_documento' => $validatedData['es_declaracion_jurada'] ? null : $validatedData['serie_documento'],
                'correlativo_documento' => $validatedData['es_declaracion_jurada'] ? null : $validatedData['correlativo_documento'],
            ]);

            $gasto = Gasto::create($dataToCreate);

            $this->registrarHistorial($gasto, 'Creado', $gasto->estado, $user->id, 'Gasto registrado.');

            return response()->json(['message' => 'Gasto registrado exitosamente.', 'gasto' => $gasto->load('registrador')], 201);
        });
    }

    /**
     * Paso 2: Aprueba un gasto. (Acción del Jefe de Área)
     */
    public function approve(Request $request, Gasto $gasto)
    {
        $user = Auth::user();

        // 1. Autorización: ¿Quién puede aprobar?
        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            return response()->json(['message' => 'No tienes permiso para aprobar este gasto.'], 403);
        }

        // 2. Validación de Estado: ¿Se puede aprobar este gasto?
        if ($gasto->estado !== 'Pendiente de Aprobación') {
            return response()->json(['message' => 'El gasto no puede ser aprobado porque no está pendiente.'], 409); // 409 Conflict
        }

        //3. Validación de datos para el tipo de cambio si la moneda es USD
        $validationRules = [];
        if ($gasto->moneda === 'USD') {
            $validationRules['tipo_cambio'] = 'required|numeric|min:0.0001';
        }
        $request->validate($validationRules);
        // 4. Ejecución
        return DB::transaction(function () use ($gasto, $user, $request) {
            $montoADescontar = 0;
            $tipoCambio = null;

            if ($gasto->moneda === 'PEN') {
                $montoADescontar = $gasto->monto_total;
            } else { // Moneda es USD
                $tipoCambio = $request->input('tipo_cambio');
                $montoADescontar = $gasto->monto_total * $tipoCambio;
            }

            // Business validation: Consultar saldo de fondos con el monto final en PEN
            if ($gasto->fondoEfectivo->monto_disponible < $montoADescontar) {
                throw ValidationException::withMessages([
                    'monto_total' => 'El fondo no tiene saldo suficiente (S/. ' . number_format($gasto->fondoEfectivo->monto_disponible, 2) . ') para cubrir el monto convertido de S/. ' . number_format($montoADescontar, 2) . '.'
                ]);
            }

            // Ejecución
            $estadoAnterior = $gasto->estado;
            $gasto->estado = 'Pendiente de Validación Contable';
            $gasto->id_jefe_aprobador = $user->id;
            $gasto->tipo_cambio = $tipoCambio;
            $gasto->monto_final_pen = $montoADescontar;
            $gasto->save();

            $gasto->fondoEfectivo->decrement('monto_disponible', $montoADescontar);

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Gasto aprobado por Jefe de Área.'));

            return response()->json(['message' => 'Gasto aprobado exitosamente.', 'gasto' => $gasto]);
        });
    }
    /**
     *  Valida, contabiliza y descuenta un gasto. (Acción de Administración)
     */
    public function validateAndAccount(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        $this->authorize('validate', $gasto);

        if ($gasto->estado !== 'Pendiente de Validación Contable') {
            return response()->json(['message' => 'Este gasto no está pendiente de validación contable.'], 409);
        }

        $validationRules = [];
        if ($gasto->moneda === 'USD') {
            $validationRules['tipo_cambio'] = 'required|numeric|min:0.0001';
        }
        $validated = $request->validate($validationRules);

        return DB::transaction(function () use ($gasto, $user, $validated, $request) {
            $tipoCambioOficial = $gasto->moneda === 'USD' ? $validated['tipo_cambio'] : null;
            $montoFinalPen = $gasto->moneda === 'PEN' ? $gasto->monto_total : ($gasto->monto_total * $tipoCambioOficial);

            // La validación de saldo y el descuento ocurren AQUI, en el paso final.
            if ($gasto->fondoEfectivo->monto_disponible < $montoFinalPen) {
                throw ValidationException::withMessages([
                    'monto_total' => 'El fondo no tiene saldo suficiente para cubrir el monto final de S/. ' . number_format($montoFinalPen, 2)
                ]);
            }

            $gasto->fondoEfectivo->decrement('monto_disponible', $montoFinalPen);

            $estadoAnterior = $gasto->estado;
            $gasto->estado = 'Contabilizado';
            $gasto->id_validador_adm = $user->id;
            $gasto->tipo_cambio = $tipoCambioOficial;
            $gasto->monto_final_pen = $montoFinalPen;
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Validado y contabilizado por administración.'));

            return response()->json(['message' => 'Gasto validado y contabilizado exitosamente.', 'gasto' => $gasto]);
        });
    }

    public function rejectByJefe(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        $request->validate(['comentario' => 'required|string|max:2000']);
        $comentario = $request->input('comentario');

        // 1. Autorización
        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            return response()->json(['message' => 'No tienes permiso para rechazar este gasto.'], 403);
        }

        // 2. Validación de Estado
        if ($gasto->estado !== 'Pendiente de Aprobación') {
            return response()->json(['message' => 'Solo se pueden rechazar gastos que estén pendientes.'], 409);
        }

        // 3. Ejecución
        $estadoAnterior = $gasto->estado;
        $gasto->estado = 'Rechazado';
        $gasto->motivo_rechazo = $comentario;
        $gasto->save();

        // No se revierte dinero porque nunca se descontó del fondo.
        $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $comentario);

        return response()->json(['message' => 'Gasto rechazado exitosamente.', 'gasto' => $gasto]);
    }

    /**
     * Paso 3: Observa un gasto. (Acción de Administración)
     */
    public function observe(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'No tienes permiso para observar gastos.'], 403);
        }

        // Se puede observar un gasto que está pendiente de aprobación o de validación.
        if (!in_array($gasto->estado, ['Pendiente de Aprobación', 'Pendiente de Validación Contable'])) {
            return response()->json(['message' => 'Solo se pueden observar gastos que estén pendientes de procesar.'], 409);
        }
        
        $request->validate(['comentario' => 'required|string|max:2000']);

        return DB::transaction(function () use ($gasto, $user, $request) {
            $estadoAnterior = $gasto->estado;
            
            // Si el gasto ya había sido aprobado por un jefe, se anula esa aprobación para reiniciar el flujo.
            if ($gasto->id_jefe_aprobador) {
                // También es importante revertir el monto del fondo si ya se había descontado.
                if ($gasto->monto_final_pen) {
                    $gasto->fondoEfectivo()->increment('monto_disponible', $gasto->monto_final_pen);
                }
                $gasto->id_jefe_aprobador = null;
                $gasto->monto_final_pen = null;
                $gasto->tipo_cambio = null;
            }

            $gasto->estado = 'Observado'; // Estado correcto según la migración.
            $gasto->motivo_observacion_adm = $request->comentario;
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, "Observado por ADM: " . $request->comentario);
            
            return response()->json(['message' => 'Gasto observado. El jefe de área será notificado para gestionar la corrección.', 'gasto' => $gasto]);
        });
    }

    /**
     * Paso 4 (Parte 1): Devuelve un gasto observado al colaborador. (Acción del Jefe de Área)
     */
    public function returnToCollaborator(Request $request, Gasto $gasto)
    {
        $user = Auth::user(); // Este es el Jefe de Área
        
        // 1. Autorización: Solo el jefe de área puede realizar esta acción.
        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            return response()->json(['message' => 'No tienes permiso para gestionar este gasto observado.'], 403);
        }

        // 2. Validación de Estado: La acción solo es válida si el gasto está 'Observado'.
        if ($gasto->estado !== 'Observado') {
            return response()->json(['message' => 'Solo se pueden gestionar gastos que han sido observados.'], 409);
        }

        $request->validate(['comentario' => 'required|string|max:2000']);
        
        // 3. Ejecución: No se cambia el estado. Solo se añade la directriz del jefe al historial.
        // El estado 'Observado' permanece, pero ahora el colaborador verá esta nueva directriz.
        $this->registrarHistorial(
            $gasto, 
            $gasto->estado, // El estado no cambia
            $gasto->estado, 
            $user->id, 
            "Directriz del Jefe: " . $request->input('comentario')
        );

        return response()->json(['message' => 'Directriz enviada al colaborador para su corrección.', 'gasto' => $gasto]);
    }

    /**
     * Paso 4 (Parte 2): El colaborador corrige y reenvía el gasto.
     */
    public function resubmit(Request $request, Gasto $gasto)
    {
        $user = Auth::user(); // Este es el Colaborador o Jefe que corrige.

        // 1. Autorización: Solo el registrador original puede corregir su gasto.
        if ($user->id !== $gasto->id_registrador) {
            return response()->json(['message' => 'No tienes permiso para corregir este gasto.'], 403);
        }
        
        // 2. Validación de Estado: El gasto debe estar 'Observado' para ser corregido.
        if ($gasto->estado !== 'Observado') {
            return response()->json(['message' => 'Este gasto no se encuentra en estado de corrección.'], 409);
        }
        
        // 3. Validación de Datos: El comentario de descargo es obligatorio.
        $validatedData = $request->validate([
            'comentario' => 'required|string|max:2000',
            // Aquí se podrían añadir otros campos que el usuario puede editar si fuera necesario.
        ]);

        return DB::transaction(function () use ($gasto, $user, $request, $validatedData) {
            $estadoAnterior = $gasto->estado;

            // Al reenviar, el estado vuelve al inicio del ciclo de aprobación.
            $gasto->estado = 'Pendiente de Aprobación';
            $gasto->motivo_observacion_adm = null; // Limpiar la observación anterior.
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, "Descargo/Corrección: " . $validatedData['comentario']);
            
            return response()->json(['message' => 'Gasto corregido y reenviado para aprobación.', 'gasto' => $gasto]);
        });
    }

    // MÉTODOS ADICIONALES PARA COMPLETAR EL FLUJO

    /**
     * Finaliza un gasto marcándolo como 'Contabilizado'. (Acción de Administración)
     */
    public function finalizeAsAccounted(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        // Autorización y Validación de Estado
        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }
        if ($gasto->estado !== 'Pendiente de Validación Contable') {
            return response()->json(['message' => 'Solo se pueden contabilizar gastos aprobados.'], 409);
        }

        $estadoAnterior = $gasto->estado;
        $gasto->update(['estado' => 'Contabilizado']);
        $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Gasto procesado y cerrado para contabilidad.'));

        return response()->json(['message' => 'Gasto marcado como Contabilizado.', 'gasto' => $gasto]);
    }

    //Rechaza un gasto de forma definitiva. (Acción de Administración)
    public function rejectFinal(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        $request->validate(['comentario' => 'required|string|max:2000']);
        $comentario = $request->input('comentario');

        // 1. Autorización
        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }

        // 2. Validación de Estado (Un admin puede rechazar un gasto aprobado que considere incorrecto)
        if ($gasto->estado !== 'Pendiente de Validación Contable') {
            return response()->json(['message' => 'Solo se pueden rechazar gastos que han sido aprobados por la jefatura.'], 409);
        }

        // 3. Ejecución
        DB::transaction(function () use ($gasto, $user, $request) {
            $estadoAnterior = $gasto->estado;

            // Revert the money to the fund, using the final discounted amount.
            $gasto->fondoEfectivo->increment('monto_disponible', $gasto->monto_final_pen);

            $gasto->estado = 'Rechazado';
            $gasto->motivo_observacion_adm = $request->comentario;
            $gasto->id_jefe_aprobador = null;
            $gasto->tipo_cambio = null;
            $gasto->monto_final_pen = null;
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->comentario);
        });

        return response()->json(['message' => 'Gasto rechazado definitivamente.', 'gasto' => $gasto]);
    }
    public function misGastos()
    {
        $user = Auth::user();
        $gastos = Gasto::with('fondoEfectivo:id_fondo,codigo_fondo')
            ->where('id_registrador', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($gastos);
    }

    /**
     * Helper para registrar en el historial de manera consistente.
     */
    private function registrarHistorial(Gasto $gasto, string $estadoAnterior, string $estadoNuevo, int $userId, ?string $comentario)
    {
        HistorialAprobacionGasto::create([
            'id_gasto' => $gasto->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'id_usuario_accion' => $userId,
            'comentario' => $comentario,
            'fecha_cambio' => now(),
        ]);
    }

    /**
     * Muestra un gasto específico.
     */
    public function show(Gasto $gasto)
    {
        return response()->json($gasto->load(['registrador.role', 'registrador.area', 'jefeAprobador', 'cuentaContable', 'historial.usuarioAccion']));
    }

    /**
     * Elimina un gasto.
     */
    public function destroy(Gasto $gasto)
    {
        $user = Auth::user();
        if (($gasto->estado === 'Pendiente de Aprobación' && $user->id === $gasto->id_registrador) || $user->hasRole('super_admin')) {
            DB::transaction(function () use ($gasto) {
                if ($gasto->ruta_evidencia) {
                    Storage::disk('public')->delete($gasto->ruta_evidencia);
                }
                $gasto->historial()->delete();
                $gasto->delete();
            });
            return response()->json(['message' => 'Gasto eliminado exitosamente.']);
        }
        return response()->json(['message' => 'No tienes permiso para eliminar este gasto o ya no se encuentra en un estado que permita su eliminación.'], 403);
    }
}
