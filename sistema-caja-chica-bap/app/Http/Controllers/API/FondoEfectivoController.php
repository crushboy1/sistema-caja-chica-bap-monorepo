<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FondoEfectivo;
use App\Models\SolicitudFondo;
use App\Models\HistorialReposicion;
use App\Models\Gasto;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class FondoEfectivoController extends Controller
{
    /**
     * Muestra una lista de todos los fondos de efectivo.
     * La visibilidad de los fondos depende del rol del usuario autenticado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $query = FondoEfectivo::query();

        // Carga la solicitud de apertura y sus relaciones para mostrar en la tabla principal.
        $query->with([
            'responsable:id,name,last_name,email,cargo',
            'area:id,name',
            'solicitudApertura' => function ($q) {
                // Se asegura de seleccionar los campos necesarios, incluyendo las claves foráneas para las relaciones anidadas.
                $q->select('id', 'codigo_solicitud', 'id_solicitante', 'id_revisor_adm', 'id_aprobador_gerente')
                    ->with(['solicitante:id,name,last_name', 'revisorAdm:id,name,last_name', 'aprobadorGerente:id,name,last_name', 'gastosProyectados']);
            }
        ]);

        // Lógica de visibilidad por rol
        if ($user->role->name === 'super_admin' || $user->role->name === 'gerente_general' || $user->role->name === 'jefe_administracion') {
            // Acceso total para roles de administración.
        } elseif ($user->role->name === 'jefe_area' || $user->role->name === 'colaborador') {
            // Un usuario regular solo ve los fondos de su área o de los que es responsable.
            $query->where(function ($q) use ($user) {
                $q->where('id_area', $user->area_id)
                    ->orWhere('id_responsable', $user->id);
            });
        } else {
            return response()->json(['message' => 'Acceso denegado. Rol no reconocido.'], 403);
        }

        // Aplicar filtros adicionales de la request
        if ($request->filled('estado') && $request->estado !== 'Todos') {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('codigo_fondo')) {
            $query->where('codigo_fondo', 'like', '%' . $request->codigo_fondo . '%');
        }
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('fecha_apertura', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('fecha_apertura', '<=', $request->fecha_fin);
        }
        if ($request->filled('responsable_name')) {
            $searchTerm = strtolower($request->responsable_name);
            $query->whereHas('responsable', function ($q) use ($searchTerm) {
                $q->where(DB::raw('LOWER(name)'), 'like', '%' . $searchTerm . '%')
                    ->orWhere(DB::raw('LOWER(last_name)'), 'like', '%' . $searchTerm . '%');
            });
        }
        if (($user->role->name === 'super_admin' || $user->role->name === 'gerente_general' || $user->role->name === 'jefe_administracion') && $request->filled('area_id')) {
            $query->where('id_area', $request->area_id);
        }

        // Ordenamiento por código de fondo para mostrar los más recientes primero.
        $fondos = $query->orderBy('codigo_fondo', 'desc')->get();

        return response()->json([
            'message' => 'Fondos de efectivo obtenidos exitosamente.',
            'fondos' => $fondos,
        ]);
    }
    public function getProyeccionesPendientes(FondoEfectivo $fondo)
    {
        $user = Auth::user();

        // 1. VALIDACIÓN DE PERMISOS
        if ($user->area_id !== $fondo->id_area && !$user->hasAnyRole(['super_admin', 'jefe_administracion'])) {
            // Excepción para fondos de proyecto
            if ($fondo->tipo_fondo !== 'Proyecto' || !$fondo->proyecto->areas->contains($user->area_id)) {
                return response()->json(['message' => 'No tienes permiso para acceder a las proyecciones de este fondo.'], 403);
            }
        }

        // 2. OBTENER PROYECCIONES CON CÁLCULO DE GASTOS
        $fondo->load('solicitudApertura.gastosProyectados');

        if (!$fondo->solicitudApertura) {
            return response()->json([]);
        }

        // 3. OBTENER GASTOS YA DECLARADOS
        // Suma todos los gastos que ya se han hecho contra este fondo, agrupados por el gasto proyectado.
        $gastosRealizados = Gasto::where('id_fondo_efectivo', $fondo->id_fondo)
            ->where('estado', '!=', 'Rechazado')
            ->select('id_gasto_proyectado', DB::raw('SUM(monto_total) as total_gastado'))
            ->groupBy('id_gasto_proyectado')
            ->get()
            ->keyBy('id_gasto_proyectado');

        // 4. CALCULAR SALDO RESTANTE Y FILTRAR
        $proyeccionesPendientes = $fondo->solicitudApertura->gastosProyectados->map(function ($proyeccion) use ($gastosRealizados) {
            $montoEstimado = $proyeccion->pivot->monto_estimado;
            $totalGastado = $gastosRealizados->get($proyeccion->id_gasto_proyectado)['total_gastado'] ?? 0;
            $saldoRestante = $montoEstimado - $totalGastado;

            // Devolvemos la proyección solo si todavía tiene saldo.
            if ($saldoRestante > 0.005) {
                return [
                    'id' => $proyeccion->id_gasto_proyectado,
                    'descripcion_gasto' => $proyeccion->descripcion,
                    'monto_proyectado' => (float) $montoEstimado,
                    'saldo_restante' => (float) $saldoRestante,
                ];
            }
            return null;
        })->filter(); // filter() sin argumentos elimina los valores nulos

        return response()->json($proyeccionesPendientes->values()->all());
    }

    /**
     * Obtiene los fondos activos para el área del usuario.
     * Este método sigue siendo útil para que el usuario seleccione su fondo activo.
     */
    public function getFondosActivosParaUsuario()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // 1. Obtener los IDs de los proyectos en los que participa el área del usuario.
        $proyectosDelAreaIds = DB::table('area_proyecto')
            ->where('id_area', $user->area_id)
            ->pluck('id_proyecto');

        // 2. Construir la consulta principal.
        $fondos = FondoEfectivo::where('estado', 'Activo')
            ->where(function ($query) use ($user, $proyectosDelAreaIds) {
                // Condición 1: El fondo pertenece directamente al área del usuario (Regular o Excepcional).
                $query->where('id_area', $user->area_id);
                // Condición 2: O el fondo es de tipo 'Proyecto' y el área del usuario participa en ese proyecto.
                $query->orWhere(function ($subQuery) use ($proyectosDelAreaIds) {
                    $subQuery->where('tipo_fondo', 'Proyecto')
                        ->whereIn('id_proyecto', $proyectosDelAreaIds);
                });
            })
            ->select('id_fondo', 'codigo_fondo', 'monto_disponible', 'monto_aprobado', 'tipo_fondo')
            ->get();

        return response()->json($fondos);
    }

    /**
     *
     * @param  int  $id_fondo
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTimeline($id_fondo)
    {
        $user = Auth::user();
        $fondo = FondoEfectivo::findOrFail($id_fondo);

        $puedeVer = false;
        if ($user->hasAnyRole(['super_admin', 'jefe_administracion', 'gerente_general'])) {
            $puedeVer = true;
        } elseif ($user->hasRole('jefe_area') && $user->area_id === $fondo->id_area) {
            $puedeVer = true;
        } elseif ($user->id === $fondo->id_responsable) {
            $puedeVer = true;
        }

        if (!$puedeVer) {
            return response()->json(['message' => 'No tienes permiso para ver el historial de este fondo.'], 403);
        }

        // 1. Obtener historial de solicitudes (Apertura, Incremento, etc.)
        $historialSolicitudes = SolicitudFondo::where(function ($query) use ($fondo) {
            $query->where('id', $fondo->id_solicitud_apertura)
                ->orWhere('id_solicitud_original', $fondo->id_solicitud_apertura);
        })
            ->where('estado', 'Aprobada')
            ->with('solicitante:id,name,last_name')
            ->get()
            ->map(function ($solicitud) {
                return [
                    'id' => 'solicitud-' . $solicitud->id,
                    'tipo' => $solicitud->tipo_solicitud,
                    'fecha' => $solicitud->updated_at,
                    'monto' => $solicitud->monto_solicitado,
                    'motivo' => $solicitud->motivo_detalle,
                    'usuario' => $solicitud->solicitante->name . ' ' . $solicitud->solicitante->last_name,
                ];
            });

        // 2. Obtener historial de reposiciones desde la nueva tabla
        $historialReposiciones = $fondo->historialReposiciones()
            ->with('usuarioAccion:id,name,last_name')
            ->get()
            ->map(function ($reposicion) {
                return [
                    'id' => 'reposicion-' . $reposicion->id,
                    'tipo' => 'Reposición',
                    'fecha' => $reposicion->fecha_reposicion,
                    'monto' => $reposicion->monto_repuesto,
                    'motivo' => $reposicion->comentario ?? 'Reposición de gastos contabilizados.',
                    'usuario' => $reposicion->usuarioAccion->name . ' ' . $reposicion->usuarioAccion->last_name,
                ];
            });

        // 3. Unificar y ordenar la línea de tiempo completa
        $timeline = $historialSolicitudes->concat($historialReposiciones)->sortByDesc('fecha');

        return response()->json(['timeline' => $timeline->values()->all()]);
    }

    /**
     * Muestra un fondo de efectivo específico por su ID.
     *
     * @param  int  $id_fondo
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id_fondo)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            // Ahora incluimos todas las relaciones necesarias para la vista de detalle
            $fondo = FondoEfectivo::with([
                'responsable:id,name,last_name,email,cargo',
                'area:id,name',
                'solicitudApertura' => function ($query) {
                    $query->select('id', 'codigo_solicitud', 'tipo_solicitud', 'monto_solicitado', 'estado', 'id_revisor_adm', 'id_aprobador_gerente');
                    $query->with([
                        'gastosProyectados:id,id_solicitud_fondo,descripcion_gasto,monto_estimado',
                        'revisorAdm:id,name,last_name',
                        'aprobadorGerente:id,name,last_name',
                        'historialEstados' => function ($qHist) {
                            $qHist->orderBy('created_at', 'asc')->with('usuarioAccion:id,name,last_name');
                        }
                    ]);
                }
            ])->findOrFail($id_fondo);

            // Validar permisos de visualización
            if (!($user->hasRole('super_admin') ||
                $user->hasRole('gerente_general') ||
                $user->hasRole('jefe_administracion') ||
                ($user->hasRole('jefe_area') && ($user->area_id === $fondo->id_area || $user->id === $fondo->id_responsable)) ||
                ($user->hasRole('colaborador') && $user->id === $fondo->id_responsable))) { // Agregado para colaborador
                return response()->json(['message' => 'Acceso denegado. No tienes permisos para ver este fondo de efectivo.'], 403);
            }

            return response()->json([
                'message' => 'Fondo de efectivo obtenido exitosamente.',
                'fondo' => $fondo,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Fondo de efectivo no encontrado.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al obtener el fondo de efectivo.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(), // Para depuración
            ], 500);
        }
    }

    /**
     * Almacena un nuevo fondo de efectivo.
     * Aunque la creación principal ocurre a través de SolicitudFondoController,
     * se implementa este método para permitir la creación directa (ej. por un super_admin)
     * y mantener la coherencia del recurso RESTful.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!($user->hasRole('super_admin') || $user->hasRole('jefe_administracion'))) {
            return response()->json(['message' => 'Acceso denegado. Solo un Super Administrador o Jefe de Administración pueden crear fondos directamente.'], 403);
        }

        try {
            $request->validate([
                'id_responsable' => 'required|exists:users,id',
                'id_area' => 'required|exists:areas,id',
                'monto_aprobado' => 'required|numeric|min:0.01',
                'fecha_apertura' => 'required|date',
                'estado' => 'required|in:Activo,Cerrado', // Solo Activo o Cerrado al crear
                // 'id_solicitud_apertura' => 'nullable|exists:solicitudes_fondos,id', // Opcional si se crea directamente
            ]);

            DB::beginTransaction();

            $fondo = FondoEfectivo::create([
                'codigo_fondo' => FondoEfectivo::generateUniqueFondoCode(), // Utiliza el método estático del modelo
                'id_responsable' => $request->id_responsable,
                'id_area' => $request->id_area,
                'monto_aprobado' => $request->monto_aprobado,
                'monto_disponible' => $request->monto_aprobado,
                'fecha_apertura' => $request->fecha_apertura,
                'estado' => $request->estado,
                'id_solicitud_apertura' => $request->id_solicitud_apertura ?? null, // Permite asignar si viene de una solicitud
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Fondo de efectivo creado exitosamente.',
                'fondo' => $fondo,
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error de validación.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear fondo de efectivo directamente: ' . $e->getMessage(), ['user_id' => $user->id, 'request' => $request->all(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Ocurrió un error al crear el fondo de efectivo.', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * Actualiza un fondo de efectivo existente.
     * Este método permite la modificación directa de los atributos de un fondo (ej. monto, estado).
     * Las actualizaciones por solicitud (incremento/decremento/cierre) se manejan por el modelo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id_fondo
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, $id_fondo)
    {
        $user = Auth::user();
        if (!($user->hasRole('super_admin') || $user->hasRole('jefe_administracion'))) {
            return response()->json(['message' => 'Acceso denegado. Solo un Super Administrador o Jefe de Administración pueden actualizar fondos directamente.'], 403);
        }

        try {
            $fondo = FondoEfectivo::findOrFail($id_fondo);

            $request->validate([
                'id_responsable' => 'sometimes|required|exists:users,id',
                'id_area' => 'sometimes|required|exists:areas,id',
                'monto_aprobado' => 'sometimes|required|numeric|min:0',
                'fecha_apertura' => 'sometimes|required|date',
                'estado' => 'sometimes|required|in:Activo,Cerrado',
                'fecha_cierre' => 'nullable|date', // Se puede setear a null si el fondo se reactiva
                'motivo_cierre' => 'nullable|string|max:1000',
                // 'id_solicitud_apertura' no debería ser actualizable aquí, ya que es el origen del fondo
            ]);

            DB::beginTransaction();

            $fondo->fill($request->only([
                'id_responsable',
                'id_area',
                'monto_aprobado',
                'fecha_apertura',
                'estado',
                'fecha_cierre',
                'motivo_cierre',
            ]));

            // Lógica para manejar la transición a 'Cerrado'
            if ($fondo->isDirty('estado') && $fondo->estado === 'Cerrado') {
                $fondo->fecha_cierre = $fondo->fecha_cierre ?? now()->toDateString();
                $fondo->motivo_cierre = $fondo->motivo_cierre ?? 'Cierre manual por Administración.';
                $fondo->monto_aprobado = 0.00; // Al cerrar, el monto debe ser 0
            } elseif ($fondo->isDirty('estado') && $fondo->estado === 'Activo') {
                // Si se reactiva un fondo, limpiar fecha y motivo de cierre
                $fondo->fecha_cierre = null;
                $fondo->motivo_cierre = null;
            }

            $fondo->save();
            DB::commit();

            return response()->json([
                'message' => 'Fondo de efectivo actualizado exitosamente.',
                'fondo' => $fondo,
            ], 200);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Fondo de efectivo no encontrado.'], 404);
        } catch (ValidationException $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error de validación.', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar fondo de efectivo directamente: ' . $e->getMessage(), ['user_id' => $user->id, 'request' => $request->all(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'Ocurrió un error al actualizar el fondo de efectivo.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * NUEVO: Calcula el resumen de reposición de un fondo determinado. 
     * Esta es la forma más segura de determinar el importe a reponer.
     *
     * @param  \App\Models\FondoEfectivo  $fondo
     * @return \Illuminate\Http\JsonResponse
     */
    public function getReposicionSummary(FondoEfectivo $fondo)
    {
        $user = Auth::user();


        if (!$user->hasAnyRole(['super_admin', 'jefe_administracion'])) {
            return response()->json(['message' => 'No tienes permiso para ver el resumen de reposición.'], 403);
        }

        $montoAReponer = $fondo->gastos()
            ->where('estado', 'Contabilizado')
            ->sum('monto_total');

        return response()->json([
            'monto_asignado' => $fondo->monto_aprobado,
            'saldo_disponible_actual' => $fondo->monto_disponible,
            'monto_a_reponer' => $montoAReponer,
        ]);
    }
    /**
     * Elimina un fondo de efectivo.
     * Esta acción debe ser muy restringida, idealmente solo para super_admin y si el fondo está "Cerrado".
     * En un sistema real, la eliminación física de fondos activos es rara y se prefiere el cierre lógico.
     *
     * @param  int  $id_fondo
     * @return \Illuminate\Http\JsonResponse
     */
    public function reponer(Request $request, FondoEfectivo $fondo)
    {
        // 1. VALIDACIÓN DE PERMISOS
        $user = Auth::user();
        if (!$user->hasAnyRole(['super_admin', 'jefe_administracion'])) {
            return response()->json(['message' => 'No tienes permiso para ejecutar la reposición de este fondo.'], 403);
        }

        // 2. VALIDACIÓN DE ESTADO DEL FONDO: No se puede reponer un fondo que ya está cerrado.
        if ($fondo->estado !== 'Activo') {
            return response()->json(['message' => 'Solo se pueden reponer fondos que se encuentren en estado "Activo".'], 409);
        }

        // 3. VALIDACIÓN CRÍTICA DE GASTOS PENDIENTES: No se puede reponer si hay gastos en CUALQUIER estado intermedio.
        $estadosPendientes = ['Pendiente de Aprobación', 'Pendiente de Validación Contable', 'Observado',];
        $gastosPendientes = $fondo->gastos()->whereIn('estado', $estadosPendientes)->count();
        if ($gastosPendientes > 0) {
            return response()->json([
                'message' => "El fondo no puede ser repuesto. Tiene {$gastosPendientes} gasto(s) en proceso de aprobación o validación."
            ], 409);
        }
        // Se utiliza la misma lógica de getProyeccionesPendientes para verificar si hay saldos restantes.
        $proyeccionesConSaldo = $fondo->solicitudApertura->detallesGastosProyectados()
            ->withSum(['gastoDeclarado as gastos_declarados_sum' => function ($query) {
                $query->where('estado', '!=', 'Rechazado');
            }], 'monto_total')
            ->get()
            ->filter(function ($proyeccion) {
                $gastosSum = $proyeccion->gastos_declarados_sum ?? 0;
                $saldoRestante = $proyeccion->monto_estimado - $gastosSum;
                return $saldoRestante > 0.005;
            });

        if ($proyeccionesConSaldo->isNotEmpty()) {
            return response()->json([
                'message' => "El fondo no puede ser repuesto porque aún tiene {$proyeccionesConSaldo->count()} concepto(s) proyectado(s) pendientes de liquidar."
            ], 409); // 409 Conflict
        }

        // 4. CÁLCULO SEGURO DEL MONTO A REPONER: Se calcula en el servidor y se valida que sea mayor a cero.
        $montoAReponer = $fondo->gastos()->where('estado', 'Contabilizado')->sum('monto_total');
        if ($montoAReponer <= 0) {
            return response()->json(['message' => 'No hay monto para reponer. Asegúrate de que los gastos hayan sido marcados como "Contabilizado".'], 409);
        }

        // 5. VALIDACIÓN DE INTEGRIDAD DEL SALDO: Una salvaguarda final contra datos inconsistentes.
        if ($fondo->monto_disponible < 0) {
            Log::warning("Intento de reposición sobre fondo con saldo negativo.", ['fondo_id' => $fondo->id_fondo, 'saldo' => $fondo->monto_disponible]);
            return response()->json([
                'message' => "Error de Integridad de Datos: El saldo disponible del fondo es negativo. Por favor, contacte a soporte."
            ], 500);
        }
        // 6. EJECUCIÓN DE LA REPOSICIÓN DENTRO DE UNA TRANSACCIÓN
        return DB::transaction(function () use ($fondo, $montoAReponer, $user, $request) {
            $saldoAnterior = $fondo->monto_disponible;
            //tambien puede ir logica para generar acta de entrega pdf.
            // RESTAURACIÓN DEL SALDO
            $fondo->increment('monto_disponible', $montoAReponer);

            // REGISTRO EN HISTORIAL PARA TRAZABILIDAD
            HistorialReposicion::create([
                'id_fondo_efectivo' => $fondo->id_fondo,
                'id_usuario_accion' => $user->id,
                'monto_repuesto' => $montoAReponer,
                'saldo_anterior' => $saldoAnterior,
                'saldo_nuevo' => $fondo->monto_disponible,
                'comentario' => $request->input('comentario', 'Reposición automática del sistema.'),
                'fecha_reposicion' => now(),
            ]);

            // 7. ACTUALIZACIÓN DE GASTOS: Los gastos usados en esta reposición se marcan como "Repuesto".
            $fondo->gastos()->where('estado', 'Contabilizado')->update(['estado' => 'Repuesto']);

            Log::info("Reposición de Fondo: El usuario {$user->name} ha repuesto S/. {$montoAReponer} al fondo '{$fondo->codigo_fondo}'.");

            return response()->json([
                'message' => "El fondo {$fondo->codigo_fondo} ha sido repuesto exitosamente.",
                'fondo' => $fondo->fresh()
            ]);
        });
    }
    public function destroy($id_fondo)
    {
        try {
            $fondo = FondoEfectivo::findOrFail($id_fondo);
            $user = Auth::user();

            // Solo un super_admin puede eliminar un fondo, y preferiblemente si está en estado 'Cerrado'.
            // Si el fondo tiene gastos asociados, la eliminación debería ser aún más restringida.
            // Por ahora, asumimos que si está 'Cerrado', está listo para ser eliminado.
            if ($user->hasRole('super_admin') && $fondo->estado === 'Cerrado') {
                // Antes de eliminar el fondo, considera si debes eliminar la solicitud de apertura asociada
                // o si simplemente el fondo ya no es relevante. Por seguridad, aquí solo se elimina el fondo.
                $fondo->delete();
                return response()->json(['message' => 'Fondo de efectivo eliminado exitosamente.'], 200);
            } else {
                return response()->json(['message' => 'Acceso denegado. No tienes permisos para eliminar este fondo o su estado no lo permite.'], 403);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Fondo de efectivo no encontrado.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al eliminar el fondo de efectivo.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
}
