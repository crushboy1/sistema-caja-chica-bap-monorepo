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
            'cuentaContable:id,codigo_cuenta,descripcion',
            'fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado',
            'detalleProyectado:id,descripcion_gasto',
        ]);

        // --- LÓGICA DE VISUALIZACIÓN POR ROL Y SCOPE ---

        // CASO 1: Es un Jefe de Área en su panel de "Aprobaciones"
        if ($request->input('scope') === 'aprobaciones') {
            if ($user->hasRole('jefe_area')) {
                // --- LÓGICA CORREGIDA PARA JEFE DE ÁREA ---
                // Ahora la consulta es más directa y robusta.
                // Un Jefe de Área en su bandeja de aprobaciones debe ver:
                $query->where(function ($q) use ($user) {
                    // 1. CUALQUIER gasto de su área que esté 'Pendiente de Aprobación'.
                    // Esto incluye los gastos recién creados por colaboradores y los que han sido reenviados.
                    $q->where('estado', 'Pendiente de Aprobación')
                        ->whereHas('registrador', function ($subQ) use ($user) {
                            $subQ->where('area_id', $user->area_id);
                        });
                })->orWhere(function ($q) use ($user) {
                    // 2. O CUALQUIER gasto de su área que esté 'Observado' por ADM.
                    // (La lógica para ocultarlo después de enviar directriz se puede manejar en el frontend si es necesario,
                    // pero es mejor que el jefe vea todo lo que está en su cancha).
                    $q->where('estado', 'Observado')
                        ->whereHas('registrador', function ($subQ) use ($user) {
                            $subQ->where('area_id', $user->area_id);
                        });
                });
            } elseif ($user->hasRole('colaborador')) {
                // El COLABORADOR solo ve sus propios gastos que han sido 'Observado'.
                $query->where('estado', 'Observado')
                    ->where('id_registrador', $user->id);
            } else {
                $query->whereRaw('1 = 0');
            }

            // CASO 2: Es cualquier otro rol o vista (Trazabilidad, Auditoría, etc.)
        } else {
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
        // 1. VALIDACIÓN
        // Se actualiza la validación para esperar un array 'gastos'.
        // El asterisco (*) aplica las reglas a cada elemento del array.
        $validatedData = $request->validate([
            'id_fondo_efectivo' => ['required', 'integer', 'exists:fondo_efectivo,id_fondo'],
            'gastos' => 'required|array|min:1',
            'gastos.*.detalle_gasto_proyectado_id' => 'required|exists:detalle_gastos_proyectados,id',
            'gastos.*.fecha_documento' => 'required|date',
            'gastos.*.monto_total' => 'required|numeric|min:0.01',
            'gastos.*.id_cuenta_contable' => 'required|exists:cuentas_contables,id',
            'gastos.*.glosa' => 'required|string|max:1000',
            'gastos.*.evidencia' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB Max
            'gastos.*.es_declaracion_jurada' => 'required|boolean',
            'gastos.*.tipo_documento' => 'required_if:gastos.*.es_declaracion_jurada,false|string|max:100',
            'gastos.*.serie_documento' => 'nullable|string|max:20',
            'gastos.*.correlativo_documento' => 'nullable|string|max:50',
            'gastos.*.comentario' => 'nullable|string|max:2000',
            'gastos.*.pertenece_proyecto' => 'required|boolean',
            'gastos.*.moneda' => 'sometimes|in:PEN,USD',
        ]);

        $user = Auth::user();
        $fondo = FondoEfectivo::findOrFail($validatedData['id_fondo_efectivo']);
        $gastosParaCrear = $validatedData['gastos'];

        // 2. LÓGICA DE SALDO
        // Se mantiene tu lógica, pero ahora se calcula la suma de todos los gastos que se están enviando.
        $montoTotalDeclarado = collect($gastosParaCrear)->sum('monto_total');

        $gastosEnProceso = $fondo->gastos()->whereIn('estado', ['Pendiente de Aprobación', 'Pendiente de Validación Contable'])->sum('monto_total');
        $saldoOperativo = $fondo->monto_disponible - $gastosEnProceso;

        if ($saldoOperativo < $montoTotalDeclarado) {
            throw ValidationException::withMessages([
                'monto_total' => 'El monto total de los gastos (S/ ' . number_format($montoTotalDeclarado, 2) . ') excede el saldo operativo real del fondo (Aprox. S/. ' . number_format($saldoOperativo, 2) . ').'
            ]);
        }

        // 3. TRANSACCIÓN
        // Se envuelve toda la lógica en una transacción para garantizar la integridad de los datos.
        return DB::transaction(function () use ($request, $gastosParaCrear, $user, $fondo) {

            $gastosCreados = [];

            // Se itera sobre el array de gastos para crear cada uno.
            foreach ($gastosParaCrear as $index => $gastoData) {
                // Se busca el archivo de evidencia correspondiente por su índice.
                $evidenciaFile = $request->file("gastos.{$index}.evidencia");
                $path = $evidenciaFile->store('evidencias_gastos', 'public');

                // Se mantiene tu lógica para determinar el estado inicial.
                $estadoInicial = $user->hasRole('jefe_area') ? 'Pendiente de Validación Contable' : 'Pendiente de Aprobación';

                // Se crea el registro del gasto.
                $gasto = Gasto::create(array_merge($gastoData, [
                    'id_fondo_efectivo' => $fondo->id_fondo,
                    'id_registrador' => $user->id,
                    'ruta_evidencia' => $path,
                    'estado' => $estadoInicial,
                    'moneda' => 'PEN', // Se asume PEN según los requerimientos.
                    'id_jefe_aprobador' => $user->hasRole('jefe_area') ? $user->id : null,
                ]));

                // Se registra el evento en el historial de cada gasto individual.
                $this->registrarHistorial($gasto, 'Creado', $gasto->estado, $user->id, 'Gasto registrado.');
                $gastosCreados[] = $gasto->load('registrador');
            }

            return response()->json([
                'message' => count($gastosCreados) . ' gasto(s) ha(n) sido registrado(s) exitosamente.',
                'gastos' => $gastosCreados
            ], 201);
        });
    }

    /**
     * Paso 2: Aprueba un gasto. (Acción del Jefe de Área)
     */
    public function approve(Request $request, Gasto $gasto)
    {
        $user = Auth::user();

        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            return response()->json(['message' => 'No tienes permiso para aprobar este gasto.'], 403);
        }
        if ($gasto->estado !== 'Pendiente de Aprobación') {
            return response()->json(['message' => 'El gasto no puede ser aprobado porque no está pendiente.'], 409);
        }

        return DB::transaction(function () use ($gasto, $user, $request) {
            $estadoAnterior = $gasto->estado;
            // Lógica de Saldo: NO se descuenta el dinero aquí.
            $gasto->estado = 'Pendiente de Validación Contable';
            $gasto->id_jefe_aprobador = $user->id;
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Gasto aprobado por Jefe de Área.'));

            return response()->json(['message' => 'Gasto aprobado exitosamente. Pasa a validación contable.', 'gasto' => $gasto]);
        });
    }

    /**
     * Finaliza un gasto marcándolo como 'Contabilizado'. (Acción de Administración)
     */
    public function finalizeAsAccounted(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }
        if ($gasto->estado !== 'Pendiente de Validación Contable') {
            return response()->json(['message' => 'Solo se pueden contabilizar gastos que estén pendientes de validación contable.'], 409);
        }

        return DB::transaction(function () use ($gasto, $user, $request) {
            $fondo = $gasto->fondoEfectivo;
            $montoFinal = $gasto->monto_total;

            // Lógica de Saldo: EL DESCUENTO OCURRE AQUÍ.
            if ($fondo->monto_disponible < $montoFinal) {
                throw ValidationException::withMessages(['monto_total' => 'El fondo no tiene saldo suficiente (S/. ' . number_format($fondo->monto_disponible, 2) . ') para cubrir este gasto.']);
            }
            $fondo->decrement('monto_disponible', $montoFinal);

            $estadoAnterior = $gasto->estado;
            $gasto->estado = 'Contabilizado';
            $gasto->id_validador_adm = $user->id;
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Gasto validado y contabilizado por administración.'));

            return response()->json(['message' => 'Gasto validado y contabilizado exitosamente.', 'gasto' => $gasto]);
        });
    }

    public function rejectByJefe(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        $request->validate(['comentario' => 'required|string|max:2000']);

        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            return response()->json(['message' => 'No tienes permiso para rechazar este gasto.'], 403);
        }
        if ($gasto->estado !== 'Pendiente de Aprobación') {
            return response()->json(['message' => 'Solo se pueden rechazar gastos que estén pendientes.'], 409);
        }

        $estadoAnterior = $gasto->estado;
        $gasto->estado = 'Rechazado';
        $gasto->motivo_rechazo = $request->input('comentario');
        $gasto->save();
        $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, "Rechazado por Jefe: " . $request->input('comentario'));

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
        if (!in_array($gasto->estado, ['Pendiente de Aprobación', 'Pendiente de Validación Contable'])) {
            return response()->json(['message' => 'Solo se pueden observar gastos que estén pendientes.'], 409);
        }

        $request->validate(['comentario' => 'required|string|max:2000']);

        return DB::transaction(function () use ($gasto, $user, $request) {
            $estadoAnterior = $gasto->estado;
            // Lógica de Saldo: No se revierte dinero porque nunca se descontó.
            if ($gasto->id_jefe_aprobador) {
                $gasto->id_jefe_aprobador = null;
            }

            $gasto->estado = 'Observado';
            $gasto->motivo_observacion_adm = $request->comentario;
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, "Observado por ADM: " . $request->comentario);

            return response()->json(['message' => 'Gasto observado. El jefe de área será notificado.', 'gasto' => $gasto]);
        });
    }

    /**
     *  Observa un gasto para corrección. (Acción del Jefe de Área)
     */
    public function observeByJefe(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            return response()->json(['message' => 'No tienes permiso para observar este gasto.'], 403);
        }
        if ($gasto->estado !== 'Pendiente de Aprobación') {
            return response()->json(['message' => 'Solo puedes observar gastos de tu equipo que estén pendientes de tu aprobación.'], 409);
        }
        $request->validate(['comentario' => 'required|string|max:2000']);

        return DB::transaction(function () use ($gasto, $user, $request) {
            $estadoAnterior = $gasto->estado;
            $gasto->estado = 'Observado';
            $gasto->motivo_observacion_adm = $request->comentario; // Se reutiliza el campo de observación de ADM.
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, "Observado por Jefe de Área: " . $request->comentario);

            return response()->json(['message' => 'Gasto observado y devuelto al colaborador para corrección.', 'gasto' => $gasto]);
        });
    }

    /**
     * Paso 4 (Parte 1): Devuelve un gasto observado al colaborador. (Acción del Jefe de Área)
     */
    public function returnToCollaborator(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        if (!$user->hasRole('jefe_area') || $user->area_id !== $gasto->registrador->area_id) {
            return response()->json(['message' => 'No tienes permiso para gestionar este gasto observado.'], 403);
        }
        if ($gasto->estado !== 'Observado') {
            return response()->json(['message' => 'Solo se pueden gestionar gastos que han sido observados.'], 409);
        }
        $request->validate(['comentario' => 'required|string|max:2000']);
        $this->registrarHistorial($gasto, $gasto->estado, $gasto->estado, $user->id, "Directriz del Jefe: " . $request->input('comentario'));
        return response()->json(['message' => 'Directriz enviada al colaborador para su corrección.', 'gasto' => $gasto]);
    }

    /**
     * Paso 4 (Parte 2): El colaborador corrige y reenvía el gasto.
     */
    public function resubmit(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        if ($user->id !== $gasto->id_registrador) {
            return response()->json(['message' => 'No tienes permiso para corregir este gasto.'], 403);
        }
        if ($gasto->estado !== 'Observado') {
            return response()->json(['message' => 'Este gasto no se encuentra en estado de corrección.'], 409);
        }
        $validatedData = $request->validate(['comentario' => 'required|string|max:2000']);

        return DB::transaction(function () use ($gasto, $user, $validatedData) {
            $estadoAnterior = $gasto->estado;
            // Si el Jefe de Área fue el que registró, pasa directo a validación contable.
            // Si no, vuelve al inicio del ciclo de aprobación por el jefe.
            $gasto->estado = $gasto->registrador->hasRole('jefe_area') ? 'Pendiente de Validación Contable' : 'Pendiente de Aprobación';
            $gasto->motivo_observacion_adm = null;
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, "Descargo/Corrección: " . $validatedData['comentario']);

            return response()->json(['message' => 'Gasto corregido y reenviado para aprobación.', 'gasto' => $gasto]);
        });
    }

    // MÉTODOS ADICIONALES PARA COMPLETAR EL FLUJO

    //Rechaza un gasto de forma definitiva. (Acción de Administración)
    public function rejectFinal(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        $request->validate(['comentario' => 'required|string|max:2000']);

        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }
        if ($gasto->estado !== 'Pendiente de Validación Contable') {
            return response()->json(['message' => 'Solo se pueden rechazar gastos que ya están aprobados por jefatura.'], 409);
        }

        return DB::transaction(function () use ($gasto, $user, $request) {
            $estadoAnterior = $gasto->estado;
            // Lógica de Saldo: No se revierte dinero porque nunca se descontó.
            $gasto->estado = 'Rechazado';
            $gasto->motivo_rechazo = $request->comentario;
            $gasto->id_jefe_aprobador = null;
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, "Rechazado por ADM: " . $request->comentario);

            return response()->json(['message' => 'Gasto rechazado definitivamente.', 'gasto' => $gasto]);
        });
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
        return response()->json($gasto->load(['registrador.role', 'registrador.area', 'jefeAprobador', 'validadorAdm', 'cuentaContable', 'detalleProyectado', 'historial.usuarioAccion']));
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
