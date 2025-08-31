<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SolicitudFondo;
use App\Models\GastoProyectado;
use App\Models\FondoEfectivo;
use App\Models\Proyecto;
use App\Models\HistorialEstadoSolicitud;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class SolicitudFondoController extends Controller
{
    /**
     * Muestra una lista de todas las solicitudes de fondo.
     * La visibilidad de las solicitudes depende del rol del usuario autenticado.
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
        $user->loadMissing('role');
        $query = SolicitudFondo::query();

        // Cargar relaciones necesarias para la visualización detallada de las solicitudes
        $query->with([
            'solicitante:id,name,last_name,email,jefe_area_id,area_id,role_id,cargo',
            'solicitante.area:id,name',
            'solicitante.role:id,name,display_name',
            'area:id,name',
            'revisorAdm:id,name,last_name',
            'aprobadorGerente:id,name,last_name',
            'gastosProyectados',
            'proyecto',
            'areasParticipantes',
            // Cargar solicitudOriginal completa para mostrar detalles en la UI
            'solicitudOriginal:id,codigo_solicitud,id_solicitante,id_area,tipo_solicitud,motivo_detalle,monto_solicitado,prioridad,estado,motivo_observacion,motivo_descargo,motivo_rechazo_final,id_revisor_adm,id_aprobador_gerente,id_solicitud_original,created_at,updated_at',

            // Cargar fondoEfectivo de la solicitudOriginal y sus relaciones
            'solicitudOriginal.fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
            'solicitudOriginal.fondoEfectivo.responsable:id,name,last_name,email,cargo',
            'solicitudOriginal.fondoEfectivo.area:id,name',

            // También cargar fondoEfectivo directo (para solicitudes de Apertura propias)
            'fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
            'fondoEfectivo.responsable:id,name,last_name,email,cargo',
            'fondoEfectivo.area:id,name',

            // Cargar el historial de estados ordenado y con el usuario que realizó la acción
            'historialEstados' => function ($q) {
                $q->orderBy('created_at', 'asc')
                    ->with('usuarioAccion:id,name,last_name');
            },
        ]);
        // --- Filtrado por Rol (Backend Enforcement para Seguridad y Eficiencia) ---
        if ($user->role->name === 'jefe_area' || $user->role->name === 'colaborador') {
            $query->where('id_solicitante', $user->id);
        } elseif ($user->role->name === 'gerente_general') {
            $query->where(function ($q) use ($user) {
                $q->whereIn('estado', ['Pendiente Aprobación GG', 'Descargo Enviado GG'])
                    ->orWhere('id_aprobador_gerente', $user->id)
                    ->orWhere('id_solicitante', $user->id);
            });
        }
        // Para 'super_admin' y 'jefe_administracion', no se aplica filtro de rol aquí, ven todas.
        // --- Aplicar Filtros Adicionales de la Request (GET parameters) ---
        if ($request->has('estado') && $request->estado !== 'Todas') {
            $query->where('estado', $request->estado);
        }
        if ($request->has('tipo_solicitud') && $request->tipo_solicitud !== 'Todos') {
            $query->where('tipo_solicitud', $request->tipo_solicitud);
        }
        if ($request->has('codigo_solicitud')) {
            $query->where('codigo_solicitud', 'like', '%' . $request->codigo_solicitud . '%');
        }
        if ($request->has('solicitante_name')) {
            $searchTerm = strtolower($request->solicitante_name);
            $query->whereHas('solicitante', function ($q) use ($searchTerm) {
                $q->where(DB::raw('LOWER(name)'), 'like', '%' . $searchTerm . '%')
                    ->orWhere(DB::raw('LOWER(last_name)'), 'like', '%' . $searchTerm . '%');
            });
        }
        if ($request->has('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }
        if ($request->has('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }
        if ($request->has('area_id') && !empty($request->area_id)) {
            $query->where('id_area', $request->area_id);
        }
        $solicitudes = $query->orderBy('codigo_solicitud', 'desc')->get();

        return response()->json([
            'message' => 'Solicitudes de fondo obtenidas exitosamente.',
            'solicitudes' => $solicitudes,
        ]);
    }
    /**
     * Almacena una nueva solicitud de fondo (Apertura, Incremento, Decremento, Cierre).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        // Validar los datos de la solicitud
        $request->validate([
            'tipo_solicitud' => 'required|in:Apertura,Incremento,Decremento,Cierre',
            'tipo_fondo_solicitado' => 'required_if:tipo_solicitud,Apertura|in:Regular,Proyecto,Excepcional',
            'id_proyecto' => 'required_if:tipo_fondo_solicitado,Proyecto|exists:proyectos,id_proyecto',
            'areas_participantes' => 'nullable|array|required_if:tipo_fondo_solicitado,Proyecto',
            'areas_participantes.*' => 'exists:areas,id',
            'motivo_detalle' => 'required|string|max:1000',
            'monto_solicitado' => [
                'required',
                'numeric',
                'min:0',
                // Se añade una regla de validación personalizada (Closure).
                function ($attribute, $value, $fail) use ($request) {
                    // Solo se aplica esta regla si hay gastos proyectados (para tipos como Apertura, etc.)
                    if ($request->has('gastos_proyectados') && is_array($request->gastos_proyectados)) {
                        // Se calcula la suma de los montos estimados del array de gastos.
                        $totalProyectado = collect($request->gastos_proyectados)->sum('monto_estimado');

                        // Se comparan los valores como flotantes para evitar problemas de precisión.
                        if (floatval($value) !== floatval($totalProyectado)) {
                            // Si no coinciden, la validación falla con este mensaje.
                            $fail('El monto solicitado (S/ ' . $value . ') debe ser exactamente igual al total de gastos proyectados (S/ ' . $totalProyectado . ').');
                        }
                    }
                },
            ], // Ahora es el NUEVO MONTO TOTAL DESEADO
            'prioridad' => 'nullable|in:Baja,Media,Alta,Urgente',
            'id_solicitud_original' => 'nullable|exists:solicitudes_fondos,id',
            'gastos_proyectados' => 'required_if:tipo_solicitud,Apertura,Incremento,Decremento|array|min:1',
            'gastos_proyectados.*.gasto_proyectado_id' => 'required|exists:gastos_proyectados,id_gasto_proyectado',
            'gastos_proyectados.*.monto_estimado' => 'required|numeric|min:0.01',
        ]);

        $user = Auth::user();
        $user->loadMissing('area', 'role'); // Cargar relaciones

        // Solo el Jefe del Área de Proyectos puede crear fondos de tipo "Proyecto"
        if ($request->tipo_fondo_solicitado === 'Proyecto') {
            if (!($user->role->name === 'jefe_area' && $user->area->name === 'Proyectos')) {
                throw ValidationException::withMessages([
                    'tipo_fondo_solicitado' => 'No tienes los permisos necesarios para crear un fondo de tipo Proyecto. Debes ser Jefe del área de Proyectos.'
                ]);
            }
        }

        // Validaciones adicionales de lógica de negocio
        if (in_array($request->tipo_solicitud, ['Incremento', 'Decremento', 'Cierre'])) {
            if (!$request->id_solicitud_original) {
                throw ValidationException::withMessages(['id_solicitud_original' => 'Para solicitudes de tipo Incremento, Decremento o Cierre, el ID de la solicitud original es requerido.']);
            }
            // Validar que el fondo efectivo original exista y esté Activo
            $fondoOriginal = FondoEfectivo::where('id_solicitud_apertura', $request->id_solicitud_original)->firstOrFail();
            if (!$fondoOriginal) {
                throw ValidationException::withMessages(['id_solicitud_original' => 'No se encontró un fondo efectivo activo asociado a la solicitud original proporcionada.']);
            }
            if ($fondoOriginal->estado !== 'Activo') {
                throw ValidationException::withMessages(['id_solicitud_original' => 'El fondo efectivo asociado a la solicitud original no está activo y no puede ser modificado. Estado actual: ' . $fondoOriginal->estado]);
            }
            // --- VALIDACIÓN #1: GASTOS "EN TRÁNSITO" (A NIVEL DE FONDO) ---
            $estadosEnProceso = ['Pendiente de Aprobación', 'Pendiente de Validación DJ', 'Pendiente de Validación Contable', 'Observado'];
            $gastosPendientes = $fondoOriginal->gastos()->whereIn('estado', $estadosEnProceso)->count();

            if ($gastosPendientes > 0) {
                throw ValidationException::withMessages([
                    'id_solicitud_original' => "No se puede modificar el fondo. Tiene {$gastosPendientes} gasto(s) pendientes de aprobación, validación o corrección."
                ]);
            }
            // --- VALIDACIÓN #2: GASTOS PROYECTADOS "DESTRUIDOS" (A NIVEL DE LÍNEA DE PRESUPUESTO) ---
            // (Solo se ejecuta si no hay gastos pendientes)
            // 1. Obtener los IDs de los gastos proyectados que ya han sido ejecutados y cerrados.
            $idsGastosProyectadosEjecutados = $fondoOriginal->gastos
                ->whereIn('estado', ['Contabilizado', 'Repuesto'])
                ->pluck('id_gasto_proyectado')
                ->unique();
            // 2. Obtener los IDs de los gastos proyectados que vienen en la nueva solicitud.
            $idsGastosProyectadosNuevos = collect($request->gastos_proyectados)->pluck('gasto_proyectado_id');
            // 3. Verificar si algún gasto proyectado ejecutado está AUSENTE en la nueva lista.
            $gastosEliminados = $idsGastosProyectadosEjecutados->diff($idsGastosProyectadosNuevos);
            if ($gastosEliminados->isNotEmpty()) {
                // 4. Si se intentó eliminar uno, bloquear la operación.
                throw ValidationException::withMessages([
                    'gastos_proyectados' => 'No se puede eliminar un gasto proyectado que ya tiene gastos contabilizados. Por favor, incluya de nuevo todos los gastos proyectados que ya han sido utilizados en su solicitud.'
                ]);
            }

            // Validar que no haya solicitudes de modificación pendientes para este mismo fondo
            $existingPendingModification = SolicitudFondo::where('id_solicitud_original', $request->id_solicitud_original)
                ->whereIn('estado', ['Pendiente Aprobación ADM', 'Observada ADM', 'Pendiente Re-evaluacion', 'Aprobada ADM', 'Pendiente Aprobación GG', 'Observada GG', 'Pendiente Re-evaluacion GG'])
                ->whereIn('tipo_solicitud', ['Incremento', 'Decremento', 'Cierre'])
                ->exists();
            if ($existingPendingModification) {
                throw ValidationException::withMessages(['id_solicitud_original' => 'Ya existe una solicitud de modificación (Incremento, Decremento o Cierre) pendiente para este fondo. Por favor, espera a que se procese la solicitud existente.']);
            }
            // Validaciones específicas para el nuevo monto solicitado (total)
            if ($request->tipo_solicitud === 'Incremento') {
                if ($request->monto_solicitado <= $fondoOriginal->monto_aprobado) {
                    throw ValidationException::withMessages(['monto_solicitado' => 'Para un incremento, el Nuevo Monto Solicitado debe ser mayor que el Monto Aprobado Actual del fondo (' . number_format($fondoOriginal->monto_aprobado, 2) . ').']);
                }
            } elseif ($request->tipo_solicitud === 'Decremento') {
                if ($request->monto_solicitado >= $fondoOriginal->monto_aprobado) {
                    throw ValidationException::withMessages(['monto_solicitado' => 'Para un decremento, el Nuevo Monto Solicitado debe ser menor que el Monto Aprobado Actual del fondo (' . number_format($fondoOriginal->monto_aprobado, 2) . ').']);
                }
                if ($request->monto_solicitado < 0) {
                    throw ValidationException::withMessages(['monto_solicitado' => 'El Nuevo Monto Solicitado no puede ser negativo para un decremento.']);
                }
            } elseif ($request->tipo_solicitud === 'Cierre') {
                if (floatval($request->monto_solicitado) !== 0.00) {
                    throw ValidationException::withMessages(['monto_solicitado' => 'Para solicitudes de tipo Cierre, el monto solicitado debe ser 0.']);
                }
            }
        } else { // Si es tipo_solicitud 'Apertura'
            if ($request->monto_solicitado <= 0) {
                throw ValidationException::withMessages(['monto_solicitado' => 'Para Apertura, el monto solicitado debe ser mayor a 0.']);
            }
        }

        DB::beginTransaction(); // Iniciar una transacción de base de datos
        try {
            $user = Auth::user(); // Usuario autenticado es el solicitante
            // Cargar el rol del usuario para la lógica condicional
            $user->loadMissing('role');
            $initialStateInDB = 'Pendiente Aprobación ADM';
            $initialHistorialObservation = 'enviada a Administración';
            $aprobadorGerenteId = null;
            $revisorAdmId = null;

            // Usamos el nombre del rol desde la relación: $user->role->name
            $userRoleName = $user->role->name;

            $tipoFondoParaGuardar = $request->tipo_solicitud === 'Apertura'
                ? $request->tipo_fondo_solicitado
                : ($fondoOriginal ? $fondoOriginal->tipo_fondo : null);
            // --- ESCENARIO 1: Solicitud de Apertura de tipo "Proyecto" ---
            if ($tipoFondoParaGuardar === 'Proyecto') {
                $initialStateInDB = 'Aprobada';
                $initialHistorialObservation = 'aprobada automáticamente (Fondo de Proyecto)';
                $revisorAdmId = $user->id;
                $aprobadorGerenteId = $user->id;
            } elseif ($userRoleName === 'gerente_general') {
                $initialStateInDB = 'Aprobada';
                $initialHistorialObservation = 'aprobada automáticamente (solicitud de Gerente General)';
                $revisorAdmId = $user->id;
                $aprobadorGerenteId = $user->id;
            } elseif ($userRoleName === 'jefe_administracion' || $userRoleName === 'super_admin') {
                $initialStateInDB = 'Pendiente Aprobación GG';
                $initialHistorialObservation = 'enviada directamente a Gerencia General';
                $revisorAdmId = $user->id;

                // NUEVA LÓGICA: Flujo especial para Decremento/Cierre iniciado por un Administrador
            } elseif (in_array($request->tipo_solicitud, ['Decremento']) && ($userRoleName === 'jefe_administracion' || $userRoleName === 'super_admin')) {
                $initialStateInDB = 'Pendiente Aprobación GG';
                $initialHistorialObservation = 'enviada directamente a Gerencia General';
                $revisorAdmId = $user->id;
            } else {
                if ($userRoleName === 'gerente_general') {
                    $initialStateInDB = 'Aprobada';
                    $initialHistorialObservation = 'aprobada automáticamente (modificación por Gerente General)';
                    $revisorAdmId = $user->id;
                    $aprobadorGerenteId = $user->id;
                } elseif ($userRoleName === 'jefe_administracion' || $userRoleName === 'super_admin') {
                    $initialStateInDB = 'Pendiente Aprobación GG';
                    $initialHistorialObservation = 'enviada directamente a Gerencia General';
                    $revisorAdmId = $user->id;
                }
                // Si es jefe_area o colaborador, se mantiene el estado por defecto: 'Pendiente Aprobación ADM'
            }
            if ($request->tipo_solicitud === 'Cierre' && in_array($userRoleName, ['jefe_area', 'gerente_general'])) {
                $initialStateInDB = 'Pendiente Aprobación ADM';
                $initialHistorialObservation = 'enviada para aprobación final de Administración';
            }
            $solicitud = SolicitudFondo::create([
                'id_solicitante' => $user->id,
                'id_area' => $user->area_id,
                'tipo_solicitud' => $request->tipo_solicitud,
                'motivo_detalle' => $request->motivo_detalle,
                'monto_solicitado' => $request->monto_solicitado,
                'prioridad' => $request->prioridad,
                'estado' => $initialStateInDB,
                'id_solicitud_original' => $request->id_solicitud_original,
                'tipo_fondo_solicitado' => $tipoFondoParaGuardar,
                'id_proyecto' => $request->id_proyecto,
                'id_revisor_adm' => $revisorAdmId,
                'id_aprobador_gerente' => $aprobadorGerenteId,
            ]);

            // Lógica para asociar las áreas participantes si es un fondo de proyecto.
            if ($request->tipo_fondo_solicitado === 'Proyecto' && $request->has('areas_participantes')) {
                $solicitud->areasParticipantes()->attach($request->input('areas_participantes'));
            }
            // Guardar los detalles de gastos proyectados (solo si se proporcionan y son relevantes para el tipo de solicitud)
            // CAMBIO 4: Lógica para no guardar gastos proyectados si es tipo Cierre
            if ($request->has('gastos_proyectados') && in_array($request->tipo_solicitud, ['Apertura', 'Incremento', 'Decremento'])) {
                $gastosParaPivot = [];
                // Se itera directamente sobre los datos del request.
                foreach ($request->gastos_proyectados as $gastoData) {
                    // El formato para attach es: [id_del_modelo_relacionado => ['columna_pivote' => valor]]
                    $gastosParaPivot[$gastoData['gasto_proyectado_id']] = ['monto_estimado' => $gastoData['monto_estimado']];
                }
                // Se usa el método attach() de Eloquent para guardar los datos en la tabla pivote.
                if (!empty($gastosParaPivot)) {
                    $solicitud->gastosProyectados()->attach($gastosParaPivot);
                }
            }

            HistorialEstadoSolicitud::create([
                'id_solicitud_fondo' => $solicitud->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'Creada', // Primer estado en el historial para trazabilidad
                'observaciones' => 'La solicitud de ' . $request->tipo_solicitud . ' fue creada por ' . $user->name . ' ' . $user->last_name . '.',
                'id_usuario_accion' => $user->id,
                'fecha_cambio' => $solicitud->created_at, // Usar la fecha de creación de la solicitud
            ]);

            HistorialEstadoSolicitud::create([
                'id_solicitud_fondo' => $solicitud->id,
                'estado_anterior' => 'Creada', // El estado anterior para esta entrada es 'Creada'
                'estado_nuevo' => $initialStateInDB, // Registrar el primer estado gestionable
                'observaciones' => 'La solicitud fue ' . $initialHistorialObservation . ' para revisión.',
                'id_usuario_accion' => $user->id,
                'fecha_cambio' => now(),
            ]);

            // --- INICIO DE CAMBIOS: CREACIÓN DE FONDO PARA PROYECTOS ---  
            $fondoGestionado = null;
            $successMessage = "¡Solicitud registrada y enviada! Código: <strong>{$solicitud->codigo_solicitud}</strong>";

            // --- ANOTACIÓN: La gestión del fondo (creación o actualización) solo ocurre si la solicitud se auto-aprueba ---
            if ($solicitud->estado === 'Aprobada') {
                $fondoGestionado = $this->manageFondoEfectivo($solicitud, $user);
                if ($fondoGestionado) {
                    $action = $solicitud->tipo_solicitud === 'Apertura' ? 'creado' : 'actualizado';
                    $successMessage = "¡Fondo {$action} exitosamente! Código de Fondo: <strong>{$fondoGestionado->codigo_fondo}</strong>";
                }
            }

            DB::commit();
            return response()->json([
                'message' => $successMessage,
                'solicitud' => $solicitud->load([
                    'solicitante.area',
                    'solicitante.role',
                    'area',
                    'gastosProyectados',
                    'areasParticipantes',
                    'proyecto',
                    'solicitudOriginal:id,codigo_solicitud,id_solicitante,id_area,tipo_solicitud,motivo_detalle,monto_solicitado,prioridad,estado,motivo_observacion,motivo_descargo,motivo_rechazo_final,id_revisor_adm,id_aprobador_gerente,id_solicitud_original,created_at,updated_at',
                    'solicitudOriginal.fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
                    'solicitudOriginal.fondoEfectivo.responsable:id,name,last_name,email,cargo',
                    'solicitudOriginal.fondoEfectivo.area:id,name',
                    'fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
                    'fondoEfectivo.responsable:id,name,last_name,email,cargo',
                    'fondoEfectivo.area:id,name',
                    'historialEstados' => function ($q) {
                        $q->orderBy('created_at', 'asc')
                            ->with('usuarioAccion:id,name,last_name'); // Cargar el usuario que realizó la acción
                    },
                    'revisorAdm',
                    'aprobadorGerente'
                ]),
            ], 201);
        } catch (ValidationException $e) {
            DB::rollBack(); // Revertir la transacción en caso de error de validación
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir la transacción en caso de cualquier otro error
            return response()->json([
                'message' => 'Ocurrió un error al procesar la solicitud.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(), // Solo para depuración, quitar en producción
            ], 500);
        }
    }

    /**
     * Muestra una solicitud de fondo específica.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        try {
            $solicitud = SolicitudFondo::with([
                'solicitante:id,name,last_name,email,jefe_area_id,area_id,role_id,cargo',
                'solicitante.area:id,name',
                'solicitante.role:id,name,display_name',
                'area:id,name',
                'revisorAdm:id,name,last_name',
                'aprobadorGerente:id,name,last_last_name',
                'gastosProyectados',
                'proyecto',
                'areasParticipantes',
                'historialEstados' => function ($q) {
                    $q->orderBy('created_at', 'asc')
                        ->with('usuarioAccion:id,name,last_name');
                },
                // Asegurando la carga de relaciones para solicitudes de modificación
                'solicitudOriginal:id,codigo_solicitud,id_solicitante,id_area,tipo_solicitud,motivo_detalle,monto_solicitado,prioridad,estado,motivo_observacion,motivo_descargo,motivo_rechazo_final,id_revisor_adm,id_aprobador_gerente,id_solicitud_original,created_at,updated_at',
                'solicitudOriginal.fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
                'solicitudOriginal.fondoEfectivo.responsable:id,name,last_name,email,cargo',
                'solicitudOriginal.fondoEfectivo.area:id,name',
                'fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
                'fondoEfectivo.responsable:id,name,last_name,email,cargo',
                'fondoEfectivo.area:id,name',
            ])->findOrFail($id);

            // Verificar permisos de visualización
            if (
                !$user->hasRole('super_admin') &&
                !$user->hasRole('jefe_administracion') &&
                !($user->hasRole('solicitante') && $solicitud->id_solicitante === $user->id) &&
                !($user->hasRole('jefe_area') && $solicitud->solicitante && $solicitud->solicitante->jefe_area_id === $user->id) && // Es jefe de área del solicitante
                !($user->hasRole('gerente_general') && ($solicitud->estado === 'Pendiente Aprobación GG' || $solicitud->estado === 'Descargo Enviado GG' || $solicitud->id_aprobador_gerente === $user->id))
            ) {
                return response()->json(['message' => 'Acceso denegado. No tienes permisos para ver esta solicitud.'], 403);
            }

            return response()->json([
                'message' => 'Solicitud de fondo obtenida exitosamente.',
                'solicitud' => $solicitud,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Solicitud de fondo no encontrada.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al obtener la solicitud.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    /**
     * Lógica para manejar la creación/actualización de FondosEfectivo
     * cuando una solicitud de fondo es aprobada o cerrada.
     * Este método ahora DELEGA la lógica al modelo FondoEfectivo.
     *
     * @param SolicitudFondo $solicitud
     * @param mixed $user
     * @return FondoEfectivo|null El fondo efectivo creado o actualizado, o null si no aplica.
     * @throws \Exception
     */
    private function manageFondoEfectivo(SolicitudFondo $solicitud, $user): ?FondoEfectivo
    {
        try {
            if ($solicitud->tipo_solicitud === 'Apertura') {
                // Delega la creación del FondoEfectivo al método estático en el modelo FondoEfectivo
                return FondoEfectivo::crearDesdeSolicitudApertura($solicitud);
            } elseif (in_array($solicitud->tipo_solicitud, ['Incremento', 'Decremento', 'Cierre'])) {
                // Delega la actualización del FondoEfectivo al método estático en el modelo FondoEfectivo
                return FondoEfectivo::actualizarDesdeSolicitudModificacion($solicitud);
            }
            return null; // Si el tipo de solicitud no corresponde a la gestión de fondos
        } catch (ModelNotFoundException $e) {
            Log::error('Error en manageFondoEfectivo: FondoEfectivo no encontrado para la solicitud: ' . $solicitud->id, ['error' => $e->getMessage()]);
            throw new \Exception('No se pudo encontrar el fondo de efectivo original para actualizar.');
        } catch (\InvalidArgumentException $e) {
            Log::error('Error en manageFondoEfectivo: Argumento inválido recibido: ' . $e->getMessage(), ['solicitud_id' => $solicitud->id, 'error' => $e->getTraceAsString()]);
            throw new \Exception('Error de lógica interna al gestionar el fondo efectivo: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error general al gestionar FondoEfectivo para solicitud: ' . $solicitud->id, ['error' => $e->getMessage()]);
            throw new \Exception('Error interno al gestionar el fondo de efectivo: ' . $e->getMessage());
        }
    }
    /**
     * Actualiza una solicitud de fondo existente (principalmente para cambios de estado).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, $id)
    {
        Log::info('SolicitudFondoController@update - Inicio del método. Payload recibido:', $request->all());

        try {
            $user = Auth::user();

            if (!$user) {
                Log::warning('SolicitudFondoController@update - Intento de acceso no autenticado. Usuario es null.');
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            // Cargar explícitamente el rol y los permisos del usuario que realiza la acción
            $user->loadMissing('role');
            $solicitud = SolicitudFondo::with('solicitante.role')->findOrFail($id);
            Log::info('SolicitudFondoController@update - Solicitud encontrada.', ['solicitud_id' => $solicitud->id, 'estado_actual' => $solicitud->estado]);

            $oldState = $solicitud->estado; // Guardar el estado anterior para el historial
            $requestedState = $request->estado; // Capturar el estado solicitado (que puede ser un hito del historial o el estado final)

            // Validar los datos de la solicitud
            $request->validate([
                'estado' => 'required|in:Observada ADM,Aprobada ADM,Descargo Enviado ADM,Observada GG,Aprobada,Rechazada Final,Descargo Enviado GG,Pendiente Re-evaluacion,Pendiente Re-evaluacion GG',
                'motivo_observacion' => 'required_if:estado,Observada ADM,Observada GG|string|max:1000',
                'motivo_descargo' => 'required_if:estado,Descargo Enviado ADM,Descargo Enviado GG|string|max:1000',
                'motivo_rechazo_final' => 'required_if:estado,Rechazada Final|string|max:1000',
            ]);

            $newState = $oldState;
            $historialState = $requestedState;
            $observacionesHistorial = '';
            $responseMessage = 'Solicitud actualizada exitosamente.';
            $managedFondoCodigo = null;
            $userRoleName = $user->role->name;
            $solicitanteRoleName = $solicitud->solicitante->role->name ?? null;
            $isSolicitanteAdminOrSuperAdmin = ($solicitud->id_solicitante === $user->id &&
                in_array($solicitanteRoleName, ['jefe_administracion', 'super_admin']));
            $isDecrementoCierre = in_array($solicitud->tipo_solicitud, ['Decremento', 'Cierre']);

            DB::beginTransaction();
            switch ($requestedState) {
                case 'Observada ADM':
                    if (!in_array($userRoleName, ['jefe_administracion', 'super_admin'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Administración puede observar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación ADM', 'Pendiente Re-evaluacion'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser observada por Administración.'], 400);
                    }
                    if ($isSolicitanteAdminOrSuperAdmin && $isDecrementoCierre) {
                        DB::rollBack();
                        return response()->json(['message' => 'No puedes observar tu propia solicitud de Decremento/Cierre. Solo el Gerente General puede hacerlo.'], 403);
                    }

                    $solicitud->motivo_observacion = $request->motivo_observacion;
                    $solicitud->id_revisor_adm = $user->id;
                    $newState = 'Observada ADM';
                    $observacionesHistorial = 'Solicitud observada por Administración: ' . $request->motivo_observacion;
                    $responseMessage = 'Observación enviada exitosamente por Administración.';
                    break;

                case 'Aprobada ADM':
                    if (!in_array($userRoleName, ['jefe_administracion', 'super_admin'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Administración puede aprobar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación ADM', 'Pendiente Re-evaluacion'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser aprobada por Administración.'], 400);
                    }
                    if ($isSolicitanteAdminOrSuperAdmin && $isDecrementoCierre) {
                        DB::rollBack();
                        return response()->json(['message' => 'No puedes aprobar tu propia solicitud de Decremento/Cierre. Solo el Gerente General puede hacerlo.'], 403);
                    }

                    $solicitud->id_revisor_adm = $user->id;
                    $solicitud->motivo_observacion = null;
                    $solicitud->motivo_descargo = null;
                    $historialState = 'Aprobada ADM';

                    if ($solicitud->tipo_solicitud === 'Cierre') {
                        // Si es un Cierre, la aprobación de ADM es la final.
                        $newState = 'Aprobada';
                        $solicitud->id_aprobador_gerente = $user->id; // El admin actúa como aprobador final.
                        $observacionesHistorial = 'Solicitud de Cierre aprobada finalmente por Administración.';

                        $managedFondo = $this->manageFondoEfectivo($solicitud, $user);
                        if ($managedFondo) {
                            $managedFondoCodigo = $managedFondo->codigo_fondo;
                            $responseMessage = "¡Éxito! Solicitud de Cierre aprobada. El fondo {$managedFondoCodigo} ha sido cerrado.";
                        }
                    } else {
                        // LÓGICA ORIGINAL: Para Apertura, Incremento y Decremento, se mantiene el flujo existente.
                        $solicitanteIsAdmin = in_array($solicitanteRoleName, ['jefe_administracion', 'super_admin']);

                        if (in_array($solicitud->tipo_solicitud, ['Apertura', 'Incremento', 'Decremento']) && $solicitanteIsAdmin) {
                            $newState = 'Pendiente Aprobación GG';
                            $observacionesHistorial = 'Solicitud aprobada por Administración. Pasa a pendiente de aprobación de Gerencia General.';
                            $responseMessage = 'Solicitud aprobada por Administración. Enviada a Gerencia General.';
                        } elseif ($solicitud->tipo_solicitud === 'Decremento') {
                            $newState = 'Aprobada';
                            $solicitud->id_aprobador_gerente = $user->id;
                            $observacionesHistorial = 'Solicitud de ' . $solicitud->tipo_solicitud . ' aprobada finalmente por Administración.';
                            $managedFondo = $this->manageFondoEfectivo($solicitud, $user);
                            if ($managedFondo) {
                                $managedFondoCodigo = $managedFondo->codigo_fondo;
                                $responseMessage = "¡Éxito! Solicitud de " . $solicitud->tipo_solicitud . " aprobada por Administración. El fondo " . $managedFondoCodigo . " ha sido " . ($solicitud->tipo_solicitud === 'Cierre' ? 'cerrado.' : 'actualizado.');
                            }
                        } else { // Este 'else' ahora solo aplica a Apertura e Incremento para no-admins.
                            $newState = 'Pendiente Aprobación GG';
                            $observacionesHistorial = 'Solicitud aprobada por Administración. Pasa a pendiente de aprobación de Gerencia General.';
                            $responseMessage = 'Solicitud aprobada por Administración. Enviada a Gerencia General.';
                        }
                    }
                    break;

                case 'Descargo Enviado ADM':
                    if ($user->id !== $solicitud->id_solicitante && $userRoleName !== 'super_admin') {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el solicitante puede enviar un descargo.'], 403);
                    }
                    if ($oldState !== 'Observada ADM') {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en estado "Observada ADM" para enviar un descargo.'], 400);
                    }

                    $solicitud->motivo_descargo = $request->motivo_descargo;
                    $newState = 'Pendiente Re-evaluacion';
                    $historialState = 'Descargo Enviado ADM';
                    $observacionesHistorial = 'Descargo enviado por el solicitante: ' . $request->motivo_descargo . '. La solicitud vuelve a ser revisada por Administración.';
                    $responseMessage = 'Descargo enviado exitosamente a Administración.';
                    break;

                case 'Observada GG':
                    if (!in_array($userRoleName, ['gerente_general', 'super_admin'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Gerente General puede observar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación GG', 'Pendiente Re-evaluacion GG'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser observada por Gerencia.'], 400);
                    }

                    $solicitud->motivo_observacion = $request->motivo_observacion;
                    $solicitud->id_aprobador_gerente = $user->id;
                    $newState = 'Observada GG';
                    $observacionesHistorial = 'Solicitud observada por Gerencia General: ' . $request->motivo_observacion;
                    $responseMessage = 'Observación enviada exitosamente por Gerencia General.';
                    break;
                case 'Aprobada':
                    if (!in_array($userRoleName, ['gerente_general', 'super_admin'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Gerente General puede aprobar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación GG', 'Pendiente Re-evaluacion GG'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser aprobada por Gerencia.'], 400);
                    }
                    if ($isSolicitanteAdminOrSuperAdmin && $isDecrementoCierre) {
                        DB::rollBack();
                        return response()->json(['message' => 'Un usuario no puede aprobar su propia solicitud de Decremento/Cierre.'], 403);
                    }

                    $solicitud->id_aprobador_gerente = $user->id;
                    $solicitud->motivo_observacion = null;
                    $solicitud->motivo_descargo = null;
                    $newState = 'Aprobada';
                    $historialState = 'Aprobada';
                    $observacionesHistorial = 'Solicitud aprobada finalmente por Gerencia General. Proceso completado.';

                    $managedFondo = $this->manageFondoEfectivo($solicitud, $user);
                    if ($managedFondo) {
                        $managedFondoCodigo = $managedFondo->codigo_fondo;
                        $action = $solicitud->tipo_solicitud === 'Cierre' ? 'cerrado' : ($solicitud->tipo_solicitud === 'Apertura' ? 'asignado' : 'actualizado');
                        $responseMessage = "¡Éxito! Solicitud de {$solicitud->tipo_solicitud} aprobada. El fondo {$managedFondoCodigo} ha sido {$action}.";
                    }
                    break;
                case 'Descargo Enviado GG':
                    if ($user->id !== $solicitud->id_solicitante && $userRoleName !== 'super_admin') {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el solicitante puede enviar un descargo.'], 403);
                    }
                    if ($oldState !== 'Observada GG') {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en estado "Observada GG" para enviar un descargo.'], 400);
                    }

                    $solicitud->motivo_descargo = $request->motivo_descargo;
                    $newState = 'Pendiente Re-evaluacion GG';
                    $historialState = 'Descargo Enviado GG';
                    $observacionesHistorial = 'Descargo enviado por el solicitante: ' . $request->motivo_descargo . '. La solicitud vuelve a ser revisada por Gerencia General.';
                    $responseMessage = 'Descargo enviado exitosamente a Gerencia General.';
                    break;
                case 'Rechazada Final':
                    if (!in_array($userRoleName, ['jefe_administracion', 'gerente_general', 'super_admin'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo roles autorizados pueden rechazar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación ADM', 'Observada ADM', 'Descargo Enviado ADM', 'Aprobada ADM', 'Pendiente Aprobación GG', 'Observada GG', 'Descargo Enviado GG', 'Pendiente Re-evaluacion', 'Pendiente Re-evaluacion GG'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser rechazada finalmente.'], 400);
                    }
                    if ($isSolicitanteAdminOrSuperAdmin && $isDecrementoCierre) {
                        DB::rollBack();
                        return response()->json(['message' => 'No puedes rechazar tu propia solicitud de Decremento/Cierre. Solo el Gerente General puede hacerlo.'], 403);
                    }
                    if (empty($request->motivo_rechazo_final)) {
                        throw ValidationException::withMessages(['motivo_rechazo_final' => 'El motivo del rechazo final es obligatorio.']);
                    }
                    $solicitud->motivo_rechazo_final = $request->motivo_rechazo_final;
                    if ($userRoleName === 'jefe_administracion') {
                        $solicitud->id_revisor_adm = $user->id;
                    } elseif ($userRoleName === 'gerente_general') {
                        $solicitud->id_aprobador_gerente = $user->id;
                    }
                    $historialState = 'Rechazada Final';
                    $observacionesHistorial = 'Solicitud rechazada finalmente: ' . $request->motivo_rechazo_final;
                    $newState = 'Rechazada Final';
                    $responseMessage = 'Solicitud rechazada definitivamente.';
                    break;

                default:
                    DB::rollBack();
                    return response()->json(['message' => 'Transición de estado no válida.'], 400);
            }
            if ($solicitud->estado !== $newState) {
                $solicitud->estado = $newState;
                $solicitud->save();
            }

            HistorialEstadoSolicitud::create([
                'id_solicitud_fondo' => $solicitud->id,
                'estado_anterior' => $oldState,
                'estado_nuevo' => $historialState,
                'observaciones' => $observacionesHistorial . ($managedFondoCodigo ? " (Fondo: " . $managedFondoCodigo . ")" : ''),
                'id_usuario_accion' => $user->id,
                'fecha_cambio' => now(),
            ]);
            Log::info('SolicitudFondoController@update - Historial de estado registrado. Estado registrado: ' . $historialState);

            DB::commit();
            Log::info('SolicitudFondoController@update - Transacción de DB confirmada exitosamente.');

            return response()->json([
                'message' => $responseMessage,
                'solicitud' => $solicitud->load([
                    'gastosProyectados',
                    'historialEstados' => function ($q) {
                        $q->orderBy('created_at', 'asc')
                            ->with('usuarioAccion:id,name,last_name');
                    },
                    'revisorAdm',
                    'aprobadorGerente',
                    'solicitante.area',
                    'solicitante.role',
                    'area',
                    'gastosProyectados',
                    'proyecto',
                    'solicitudOriginal:id,codigo_solicitud,id_solicitante,id_area,tipo_solicitud,motivo_detalle,monto_solicitado,prioridad,estado,motivo_observacion,motivo_descargo,motivo_rechazo_final,id_revisor_adm,id_aprobador_gerente,id_solicitud_original,created_at,updated_at',
                    'solicitudOriginal.fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
                    'solicitudOriginal.fondoEfectivo.responsable:id,name,last_name,email,cargo',
                    'solicitudOriginal.fondoEfectivo.area:id,name',
                    'fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
                    'fondoEfectivo.responsable:id,name,last_name,email,cargo',
                    'fondoEfectivo.area:id,name',
                ]),
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
            Log::error('SolicitudFondoController@update - Solicitud no encontrada (404):', [
                'exception' => $e->getMessage(),
                'solicitud_id' => $id,
                'user_id' => Auth::id(),
            ]);
            return response()->json(['message' => 'Solicitud de fondo no encontrada.'], 404);
        } catch (ValidationException $e) {
            DB::rollBack();
            Log::error('SolicitudFondoController@update - Error de validación (422):', [
                'exception' => $e->getMessage(),
                'errors' => $e->errors(),
                'request_payload' => $request->all(),
                'solicitud_id' => $id,
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('SolicitudFondoController@update - Ocurrió un error inesperado (500):', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_payload' => $request->all(),
                'solicitud_id' => $id,
                'user_id' => Auth::id(),
            ]);
            return response()->json([
                'message' => 'Ocurrió un error al actualizar la solicitud.',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }
    public function editarSolicitudPendiente(Request $request, SolicitudFondo $solicitud)
    {
        $user = Auth::user();
        $user->loadMissing('role');
        $userRoleName = $user->role->name;

        // 1. Autorización: El usuario debe ser el solicitante.
        if ($user->id !== $solicitud->id_solicitante) {
            return response()->json(['message' => 'No eres el solicitante de esta solicitud.'], 403);
        }

        // --- Lógica de autorización por rol y estado ---
        $canEdit = false;
        // Solicitantes regulares (Jefe de Área, Colaborador) pueden editar solo ANTES de la revisión de ADM.
        if (in_array($userRoleName, ['jefe_area', 'colaborador']) && $solicitud->estado === 'Pendiente Aprobación ADM') {
            $canEdit = true;
        }
        // Solicitantes de alto nivel (ADM, Super Admin) pueden editar solo ANTES de la revisión de GG.
        // Esto soluciona el error de la imagen 2.
        if (in_array($userRoleName, ['jefe_administracion', 'super_admin']) && $solicitud->estado === 'Pendiente Aprobación GG') {
            $canEdit = true;
        }

        if (!$canEdit) {
            return response()->json(['message' => 'Esta solicitud no puede ser editada en su estado actual.'], 403);
        }
        // 2. Validación del formulario completo
        $validatedData = $request->validate([
            'motivo_detalle' => 'required|string|max:1000',
            'monto_solicitado' => 'required|numeric|min:0',
            'prioridad' => ['nullable', Rule::requiredIf(in_array($solicitud->tipo_solicitud, ['Incremento', 'Decremento', 'Cierre'])), 'in:Baja,Media,Alta,Urgente'],
            'tipo_fondo_solicitado' => 'required|in:Regular,Excepcional,Proyecto',
            'id_proyecto' => 'nullable|exists:proyectos,id_proyecto',
            'gastos_proyectados' => 'nullable|array',
            'gastos_proyectados.*.gasto_proyectado_id' => 'required|exists:gastos_proyectados,id_gasto_proyectado',
            'gastos_proyectados.*.monto_estimado' => 'required|numeric|min:0.01',
            'areas_participantes' => 'nullable|array|required_if:tipo_fondo_solicitado,Proyecto',
            'areas_participantes.*' => 'exists:areas,id',
        ]);

        DB::beginTransaction();
        try {
            // 3.
            $originalSolicitud = $solicitud->load('gastosProyectados', 'proyecto', 'areasParticipantes');
            $clonedOriginal = clone $originalSolicitud;

            // 4. Sincronizar los gastos proyectados 
            $updateData = $request->only(['motivo_detalle', 'monto_solicitado', 'tipo_fondo_solicitado']);
            if ($request->has('prioridad')) {
                $updateData['prioridad'] = $validatedData['prioridad'];
            }
            if ($request->has('id_proyecto')) {
                $updateData['id_proyecto'] = $validatedData['id_proyecto'];
            } else {
                // Si no se envía id_proyecto, asegurarse de que se establezca en null
                $updateData['id_proyecto'] = null;
            }
            $solicitud->update($updateData);

            // Sincronización de relaciones
            if (isset($validatedData['gastos_proyectados'])) {
                $gastosParaSincronizar = [];
                foreach ($validatedData['gastos_proyectados'] as $gasto) {
                    $gastosParaSincronizar[$gasto['gasto_proyectado_id']] = ['monto_estimado' => $gasto['monto_estimado']];
                }
                $solicitud->gastosProyectados()->sync($gastosParaSincronizar);
            }
            if ($solicitud->tipo_fondo_solicitado === 'Proyecto' && isset($validatedData['areas_participantes'])) {
                $solicitud->areasParticipantes()->sync($validatedData['areas_participantes']);
            } else {
                $solicitud->areasParticipantes()->sync([]);
            }
            // 5. Registrar los cambios en el historial.
            // Llamamos a nuestra función de ayuda para mantener el código limpio.
            $this->trackChangesAndUpdateHistory(
                $solicitud->fresh(),
                $clonedOriginal,
                $validatedData,
                $user,
                "Edición Proactiva", // Tipo de acción
                "Solicitud editada por el solicitante antes de la primera revisión.", // Detalle
                $clonedOriginal->estado,
                $clonedOriginal->estado
            );
            // Si todo sale bien, confirmamos los cambios en la base de datos.
            DB::commit();
            // Devolvemos una respuesta exitosa con la solicitud actualizada y sus relaciones.
            return response()->json([
                'message' => 'Solicitud actualizada con éxito.',
                'solicitud' => $solicitud->fresh()->load(['solicitante.area', 'area', 'gastosProyectados', 'areasParticipantes', 'historialEstados.usuarioAccion'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al editar solicitud pendiente [{$solicitud->id}]: " . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar la solicitud.', 'error' => $e->getMessage()], 500);
        }
    }
    public function editarSolicitudObservada(Request $request, SolicitudFondo $solicitud)
    {
        $user = Auth::user();
        // 1. Autorización: Se mantiene la verificación.
        if ($user->id !== $solicitud->id_solicitante || !in_array($solicitud->estado, ['Observada ADM', 'Observada GG'])) {
            return response()->json(['message' => 'Esta solicitud no puede ser editada en su estado actual.'], 403);
        }
        // 2. Validación: Se mantiene la validación completa.
        $validatedData = $request->validate([
            'motivo_detalle' => 'required|string|max:1000',
            'monto_solicitado' => 'required|numeric|min:0',
            'prioridad' => ['nullable', Rule::requiredIf(in_array($solicitud->tipo_solicitud, ['Incremento', 'Decremento', 'Cierre'])), 'in:Baja,Media,Alta,Urgente'],
            'tipo_fondo_solicitado' => 'required|in:Regular,Excepcional,Proyecto',
            'id_proyecto' => 'nullable|exists:proyectos,id_proyecto',
            'comentario_descargo' => 'nullable|string|max:1000',
            'gastos_proyectados' => 'present|array',
            'gastos_proyectados.*.gasto_proyectado_id' => 'required|exists:gastos_proyectados,id_gasto_proyectado',
            'gastos_proyectados.*.monto_estimado' => 'required|numeric|min:0.01',
            'areas_participantes' => 'nullable|array|required_if:tipo_fondo_solicitado,Proyecto',
            'areas_participantes.*' => 'exists:areas,id',
        ]);

        DB::beginTransaction();
        try {
            // 3. Capturar el estado completo ANTES de la actualización.
            // Se cargan las relaciones para asegurar que el clon tenga toda la información para comparar.
            $originalSolicitud = $solicitud->load('gastosProyectados', 'proyecto', 'areasParticipantes');
            $clonedOriginal = clone $originalSolicitud;
            $estadoAnterior = $clonedOriginal->estado; // Guardar el estado principal original.

            // 4. Actualizar la solicitud y sus detalles de forma segura.
            $updateData = $request->only(['motivo_detalle', 'monto_solicitado', 'tipo_fondo_solicitado', 'motivo_descargo']);
            if ($request->has('prioridad')) {
                $updateData['prioridad'] = $validatedData['prioridad'];
            }
            if ($request->has('id_proyecto')) {
                $updateData['id_proyecto'] = $validatedData['id_proyecto'];
            } else {
                $updateData['id_proyecto'] = null;
            }
            $solicitud->fill($updateData);
            // Sincronizar áreas y gastos
            if ($solicitud->tipo_fondo_solicitado === 'Proyecto' && isset($validatedData['areas_participantes'])) {
                $solicitud->areasParticipantes()->sync($validatedData['areas_participantes']);
            } else {
                $solicitud->areasParticipantes()->sync([]);
            }
            if (isset($validatedData['gastos_proyectados'])) {
                $gastosParaSincronizar = [];
                foreach ($validatedData['gastos_proyectados'] as $gasto) {
                    $gastosParaSincronizar[$gasto['gasto_proyectado_id']] = ['monto_estimado' => $gasto['monto_estimado']];
                }
                $solicitud->gastosProyectados()->sync($gastosParaSincronizar);
            }

            // 5. Mover la máquina de estados según quién hizo la observación original.
            $nuevoEstadoPrincipal = '';
            $estadoHistorial = '';
            if ($estadoAnterior === 'Observada ADM') {
                $nuevoEstadoPrincipal = 'Pendiente Re-evaluacion';
                $estadoHistorial = 'Descargo Enviado ADM';
            } else if ($estadoAnterior === 'Observada GG') {
                $nuevoEstadoPrincipal = 'Pendiente Re-evaluacion GG';
                $estadoHistorial = 'Descargo Enviado GG';
            }
            $solicitud->estado = $nuevoEstadoPrincipal;
            $solicitud->save();
            // 6. Registrar la acción en el historial usando la función centralizada.
            $detalleBase = "Solicitud editada para subsanar observación";
            $this->trackChangesAndUpdateHistory(
                $solicitud->fresh(), // La solicitud actualizada para reflejar todos los cambios.
                $clonedOriginal,    // La "foto" de la solicitud antes de los cambios.
                $validatedData,     // Los nuevos datos del request para la comparación.
                $user,
                "Edición por Observación",
                $detalleBase,
                $estadoAnterior,
                $estadoHistorial
            );
            DB::commit();
            return response()->json([
                'message' => 'Solicitud corregida y reenviada para aprobación.',
                'solicitud' => $solicitud->fresh()->load([
                    'solicitante.area',
                    'area',
                    'gastosProyectados',
                    'areasParticipantes',
                    'historialEstados.usuarioAccion'
                ])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al editar solicitud observada [{$solicitud->id}]: " . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar la solicitud.', 'error' => $e->getMessage()], 500);
        }
    }
    /**
     * Centraliza la lógica de registrar cambios y crear la entrada de historial.
     */
    private function trackChangesAndUpdateHistory(
        SolicitudFondo $solicitud,
        SolicitudFondo $originalSolicitud,
        array $newData,
        \App\Models\User $user,
        string $tipoAccion,
        string $detalleBase,
        ?string $estadoAnterior = null,
        ?string $estadoHistorial = null
    ) {
        $observationParts = [];

        // Parte 1: Motivo de la acción (siempre se muestra)
        $observationParts[] = "<strong>Motivo de cambio:</strong> " . e($detalleBase);

        // Parte 2: Comentario de descargo del usuario (si existe y no está ya en detalleBase)
        $comentarioDescargo = $newData['detalle_descargo'] ?? $newData['comentario_descargo'] ?? null;
        if (!empty($comentarioDescargo) && !str_contains($detalleBase, $comentarioDescargo)) {
            $observationParts[] = "<strong>Comentario del solicitante:</strong> " . e($comentarioDescargo);
        }

        // Parte 3: Lista de cambios detectados por el sistema
        $formattedChanges = $this->formatChangesForHistory($originalSolicitud, $newData);
        if (!empty($formattedChanges)) {
            $changesList = '<div style="margin: 5px 0;">';
            foreach ($formattedChanges as $change) {
                $changesList .= '<div style="margin-bottom: 2px;">' . $change . '</div>';
            }
            $changesList .= '</div>';
            $observationParts[] = "<strong>Detalle de Cambios:</strong><br>" . $changesList;
        }

        // Unir todas las partes con un salto de línea simple para mejor legibilidad
        $observacionFinal = implode('<br>', $observationParts);

        // Incrementar contador y guardar historial JSON (lógica original preservada).
        $solicitud->increment('edit_count');
        $rawChanges = array_diff_assoc($solicitud->getAttributes(), $originalSolicitud->getAttributes());
        $historialCambios = $solicitud->historial_cambios ?? [];
        $historialCambios['edicion_' . $solicitud->edit_count] = [
            'usuario' => $user->name,
            'fecha' => now()->toDateTimeString(),
            'tipo' => $tipoAccion,
            'cambios' => $rawChanges
        ];
        $solicitud->historial_cambios = $historialCambios;
        $solicitud->saveQuietly();

        // Crear la entrada en la tabla de historial con la observación formateada.
        HistorialEstadoSolicitud::create([
            'id_solicitud_fondo' => $solicitud->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoHistorial,
            'observaciones' => $observacionFinal,
            'id_usuario_accion' => $user->id,
            'fecha_cambio' => now(),
        ]);
    }
    /**
     * Función auxiliar para formatear los cambios y devolver un ARRAY de strings.
     */
    private function formatChangesForHistory(SolicitudFondo $originalSolicitud, array $newData)
    {
        $changes = [];
        $friendlyNames = [
            'motivo_detalle' => 'Motivo de la Solicitud',
            'monto_solicitado' => 'Monto Solicitado',
            'prioridad' => 'Prioridad',
            'tipo_fondo_solicitado' => 'Tipo de Fondo',
            'id_proyecto' => 'Proyecto'
        ];
        $originalAttributes = $originalSolicitud->getAttributes();
        foreach ($friendlyNames as $field => $name) {
            if (array_key_exists($field, $newData) && $originalAttributes[$field] != $newData[$field]) {
                $oldValue = $originalAttributes[$field] ?? 'vacío';
                $newValue = $newData[$field] ?? 'vacío';
                if ($field === 'id_proyecto') {
                    $oldProjectName = $originalSolicitud->proyecto->nombre ?? 'Ninguno';
                    $newProject = Proyecto::find($newValue);
                    $newProjectName = $newProject ? $newProject->nombre : 'Ninguno';
                    if ($oldProjectName !== $newProjectName) {
                        $changes[] = "<strong>{$name}</strong> cambió de '{$oldProjectName}' a '{$newProjectName}'";
                    }
                } elseif ($field === 'monto_solicitado') {
                    // CORREGIDO: Formatear montos con símbolo de soles
                    $oldValueFormatted = is_numeric($oldValue) ? 'S/ ' . number_format($oldValue, 2) : $oldValue;
                    $newValueFormatted = is_numeric($newValue) ? 'S/ ' . number_format($newValue, 2) : $newValue;
                    $changes[] = "<strong>{$name}</strong> cambió de '{$oldValueFormatted}' a '{$newValueFormatted}'";
                } else {
                    $changes[] = "<strong>{$name}</strong> cambió de '{$oldValue}' a '{$newValue}'";
                }
            }
        }
        // CORREGIDO: Mejorar el manejo de gastos proyectados
        $originalGastos = $originalSolicitud->gastosProyectados->pluck('pivot.monto_estimado', 'id_gasto_proyectado')->all();
        $newGastosData = collect($newData['gastos_proyectados'] ?? []);
        $newGastos = $newGastosData->pluck('monto_estimado', 'gasto_proyectado_id')->all();
        $gastoChanges = [];
        $allGastoIds = array_unique(array_merge(array_keys($originalGastos), array_keys($newGastos)));

        if (count($allGastoIds) > 0) {
            $gastoModels = GastoProyectado::whereIn('id_gasto_proyectado', $allGastoIds)->pluck('descripcion', 'id_gasto_proyectado');
            foreach ($allGastoIds as $id) {
                $gastoNombre = $gastoModels->get($id, "ID {$id}");
                $oldMonto = $originalGastos[$id] ?? null;
                $newMonto = $newGastos[$id] ?? null;

                if ($oldMonto === null && $newMonto !== null) {
                    $gastoChanges[] = "Se añadió <strong>{$gastoNombre}</strong> (S/ " . number_format($newMonto, 2) . ")";
                } elseif ($oldMonto !== null && $newMonto === null) {
                    $gastoChanges[] = "Se eliminó <strong>{$gastoNombre}</strong> (antes S/ " . number_format($oldMonto, 2) . ")";
                } elseif ((float)$oldMonto != (float)$newMonto) {
                    $gastoChanges[] = "<strong>{$gastoNombre}</strong> cambió de S/ " . number_format($oldMonto, 2) . " a S/ " . number_format($newMonto, 2);
                }
            }
        }
        // CORREGIDO: Mostrar cada cambio de gasto en líneas separadas
        if (!empty($gastoChanges)) {
            $changes[] = "<strong>Gastos Proyectados:</strong>";
            foreach ($gastoChanges as $gastoChange) {
                $changes[] = "• " . $gastoChange;
            }
        }
        return $changes;
    }
    /**
     * Elimina una solicitud de fondo.
     * SOLO UN SUPER_ADMIN PUEDE ELIMINAR UNA SOLICITUD EN CUALQUIER ESTADO.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $solicitud = SolicitudFondo::findOrFail($id);
            $user = Auth::user();
            $user->loadMissing('role'); // Cargar la relación del rol

            // CORRECCIÓN: Se cambia hasRole() por la verificación directa
            if ($user->role->name === 'super_admin') {
                DB::beginTransaction();

                // CORRECCIÓN: Se usa sync([]) para limpiar la tabla pivote de la relación muchos-a-muchos
                $solicitud->gastosProyectados()->sync([]);
                // Eliminar historial de estados asociado
                $solicitud->historialEstados()->delete();
                // Eliminar la solicitud principal
                $solicitud->delete();

                DB::commit();
                return response()->json(['message' => 'Solicitud de fondo eliminada exitosamente.'], 200);
            } else {
                return response()->json(['message' => 'Acceso denegado. Solo un Super Administrador puede eliminar solicitudes.'], 403);
            }
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Solicitud de fondo no encontrada.'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ocurrió un error al eliminar la solicitud.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
