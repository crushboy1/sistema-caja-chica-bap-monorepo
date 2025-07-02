<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SolicitudFondo;
use App\Models\DetalleGastoProyectado;
use App\Models\FondoEfectivo; // Importar el modelo FondoEfectivo
use App\Models\HistorialEstadoSolicitud; // Para registrar cambios de estado
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB; // Para transacciones de base de datos
use Illuminate\Support\Str; // Para funciones de cadena, útil en la generación de códigos
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;
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

        $query = SolicitudFondo::query();

        // Cargar relaciones necesarias para la visualización detallada de las solicitudes
        $query->with([
            'solicitante:id,name,last_name,email,jefe_area_id,area_id,role_id,cargo',
            'solicitante.area:id,name',
            'solicitante.role:id,name,display_name',
            'area:id,name',
            'revisorAdm:id,name,last_name',
            'aprobadorGerente:id,name,last_name',
            'detallesGastosProyectados',

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
        if ($user->hasRole('jefe_area')) {
            $query->where('id_solicitante', $user->id);
        } elseif ($user->hasRole('gerente_general')) {
            $query->where(function ($q) use ($user) {
                // El Gerente General ve solicitudes pendientes para él o que ya aprobó
                $q->whereIn('estado', ['Pendiente Aprobación GRTE', 'Descargo Enviado GRTE'])
                    ->orWhere('id_aprobador_gerente', $user->id);
            });
        } elseif ($user->hasRole('colaborador')) {
            $query->where('id_solicitante', $user->id);
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

        // Validaciones adicionales de lógica de negocio
        if (in_array($request->tipo_solicitud, ['Incremento', 'Decremento', 'Cierre'])) {
            if (!$request->id_solicitud_original) {
                throw ValidationException::withMessages(['id_solicitud_original' => 'Para solicitudes de tipo Incremento, Decremento o Cierre, el ID de la solicitud original es requerido.']);
            }
            // Validar que el fondo efectivo original exista y esté Activo
            $fondoOriginal = FondoEfectivo::where('id_solicitud_apertura', $request->id_solicitud_original)->first();
            if (!$fondoOriginal) {
                throw ValidationException::withMessages(['id_solicitud_original' => 'No se encontró un fondo efectivo activo asociado a la solicitud original proporcionada.']);
            }
            if ($fondoOriginal->estado !== 'Activo') {
                throw ValidationException::withMessages(['id_solicitud_original' => 'El fondo efectivo asociado a la solicitud original no está activo y no puede ser modificado. Estado actual: ' . $fondoOriginal->estado]);
            }
            // Validar que no haya solicitudes de modificación pendientes para este mismo fondo
            $existingPendingModification = SolicitudFondo::where('id_solicitud_original', $request->id_solicitud_original)
                ->whereIn('estado', ['Pendiente Aprobación ADM', 'Observada ADM', 'Descargo Enviado ADM', 'Aprobada ADM', 'Pendiente Aprobación GRTE', 'Observada GRTE', 'Descargo Enviado GRTE'])
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
            $initialHistorialObservation = 'enviada a Administración'; // Observación inicial para historial

            if ($request->tipo_solicitud === 'Apertura' && $request->tipo_fondo_solicitado === 'Proyecto') {
                $initialStateInDB = 'Aprobada';
                $initialHistorialObservation = 'aprobada automáticamente (Fondo de Proyecto)';
            }
            // Se mantiene tu lógica para Decremento/Cierre por parte de Admins.
            elseif (in_array($request->tipo_solicitud, ['Decremento', 'Cierre']) && ($user->hasRole('jefe_administracion') || $user->hasRole('super_admin'))) {
                $initialStateInDB = 'Pendiente Aprobación GRTE';
                $initialHistorialObservation = 'enviada directamente a Gerencia General';
            }
            $solicitud = SolicitudFondo::create([
                'id_solicitante' => $user->id, // El usuario autenticado es el solicitante
                'id_area' => $user->area_id,
                'tipo_solicitud' => $request->tipo_solicitud,
                'motivo_detalle' => $request->motivo_detalle,
                'monto_solicitado' => $request->monto_solicitado, // AHORA ES EL NUEVO MONTO TOTAL DESEADO
                'prioridad' => $request->prioridad,
                'estado' => $initialStateInDB, 
                'id_solicitud_original' => $request->id_solicitud_original,
                'tipo_fondo_solicitado' => $request->tipo_fondo_solicitado,
                'id_proyecto' => $request->id_proyecto,
            ]);

            
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
            if ($solicitud->estado === 'Aprobada') {
                FondoEfectivo::crearDesdeSolicitudApertura($solicitud);
                Log::info('Fondo de proyecto creado automáticamente.', ['solicitud_id' => $solicitud->id]);
            }
            DB::commit(); 
            return response()->json([
                'message' => 'Solicitud de fondo creada exitosamente. ',
                'solicitud' => $solicitud->load([
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
                'detallesGastosProyectados',
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
                !($user->hasRole('gerente_general') && ($solicitud->estado === 'Pendiente Aprobación GRTE' || $solicitud->estado === 'Descargo Enviado GRTE' || $solicitud->id_aprobador_gerente === $user->id))
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
            Log::info('SolicitudFondoController@update - Intentando obtener usuario autenticado.');
            $user = Auth::user();

            if (!$user) {
                Log::warning('SolicitudFondoController@update - Intento de acceso no autenticado. Usuario es null.');
                return response()->json(['message' => 'No autenticado.'], 401);
            }

            // Cargar explícitamente el rol y los permisos del usuario que realiza la acción
            $user->loadMissing('role.permissions');
            Log::info('SolicitudFondoController@update - Usuario autenticado y relaciones cargadas:', ['user_id' => $user->id, 'user_role' => $user->role->name ?? 'N/A']);


            Log::info('SolicitudFondoController@update - Intentando encontrar solicitud de fondo por ID.', ['solicitud_id' => $id]);
            // Cargar la solicitud con su solicitante para verificar roles del solicitante
            $solicitud = SolicitudFondo::with('solicitante.role')->findOrFail($id);
            Log::info('SolicitudFondoController@update - Solicitud encontrada.', ['solicitud_id' => $solicitud->id, 'estado_actual' => $solicitud->estado]);

            $oldState = $solicitud->estado; // Guardar el estado anterior para el historial
            $requestedState = $request->estado; // Capturar el estado solicitado (que puede ser un hito del historial o el estado final)

            // Validar los datos de la solicitud
            Log::info('SolicitudFondoController@update - Realizando validación de request.');
            $request->validate([
                'estado' => 'required|in:Observada ADM,Aprobada ADM,Descargo Enviado ADM,Observada GRTE,Aprobada,Rechazada Final,Descargo Enviado GRTE,Pendiente Re-evaluacion',
                'motivo_observacion' => 'required_if:estado,Observada ADM,Observada GRTE|string|max:1000',
                'motivo_descargo' => 'required_if:estado,Descargo Enviado ADM,Descargo Enviado GRTE|string|max:1000',
                'motivo_rechazo_final' => 'required_if:estado,Rechazada Final|string|max:1000',
            ]);
            Log::info('SolicitudFondoController@update - Validación de request exitosa.');

            $newState = $oldState; // Inicialmente, el nuevo estado principal es el mismo que el anterior, cambiará en el switch
            $historialState = $requestedState; // Por defecto, el estado a registrar en historial es el solicitado
            $observacionesHistorial = ''; // La variable observacionesHistorial ahora se construirá de forma dinámica
            $responseMessage = 'Solicitud actualizada exitosamente.';
            $managedFondoCodigo = null; // Variable para almacenar el código del fondo gestionado

            // CAMBIO (Parte 2): Lógica para restricciones de auto-aprobación para Jefe de Administración/Super Admin en Decremento/Cierre
            $isSolicitanteAdminOrSuperAdmin = ($solicitud->id_solicitante === $user->id &&
                ($user->hasRole('jefe_administracion') || $user->hasRole('super_admin')));
            $isDecrementoCierre = in_array($solicitud->tipo_solicitud, ['Decremento', 'Cierre']);


            DB::beginTransaction();
            switch ($requestedState) {
                case 'Observada ADM':
                    if (!($user->hasRole('jefe_administracion') || $user->hasRole('super_admin'))) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Administración puede observar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación ADM', 'Descargo Enviado ADM','Pendiente Re-evaluacion'])) {
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
                    if (!($user->hasRole('jefe_administracion') || $user->hasRole('super_admin'))) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Administración puede aprobar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación ADM', 'Descargo Enviado ADM','Pendiente Re-evaluacion'])) {
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

                    if (
                        in_array($solicitud->tipo_solicitud, ['Apertura', 'Incremento']) ||
                        ($isDecrementoCierre && $solicitud->solicitante && ($solicitud->solicitante->hasRole('jefe_administracion') || $solicitud->solicitante->hasRole('super_admin')))
                    ) {

                        $newState = 'Pendiente Aprobación GRTE';
                        $observacionesHistorial = 'Solicitud aprobada por Administración. Pasa a pendiente de aprobación de Gerencia General.';
                        $responseMessage = 'Solicitud aprobada por Administración. Enviada a Gerencia General.'; // Mensaje personalizado
                    } else if (
                        in_array($solicitud->tipo_solicitud, ['Decremento', 'Cierre']) &&
                        $solicitud->solicitante && ($solicitud->solicitante->hasRole('jefe_area') || $solicitud->solicitante->hasRole('colaborador'))
                    ) {

                        $newState = 'Aprobada'; // Aprobación final por ADM
                        $solicitud->id_aprobador_gerente = $user->id;
                        $observacionesHistorial = 'Solicitud de ' . $solicitud->tipo_solicitud . ' aprobada finalmente por Administración.';
                        // Gestionar el fondo efectivo (crear/actualizar) una vez la solicitud es aprobada finalmente por ADM
                        $managedFondo = $this->manageFondoEfectivo($solicitud, $user);
                        if ($managedFondo) {
                            $managedFondoCodigo = $managedFondo->codigo_fondo;
                            $responseMessage = "¡Éxito! Solicitud de " . $solicitud->tipo_solicitud . " aprobada por Administración. El fondo " . $managedFondoCodigo . " ha sido " . ($solicitud->tipo_solicitud === 'Cierre' ? 'cerrado.' : 'actualizado.');
                        } else {
                            $responseMessage = 'Solicitud aprobada por Administración exitosamente.';
                        }
                    } else {
                        DB::rollBack();
                        return response()->json(['message' => 'Lógica de aprobación de ADM no cubierta para este tipo de solicitud y solicitante.'], 400);
                    }
                    break;

                case 'Descargo Enviado ADM':
                    Log::info('SolicitudFondoController@update - Procesando Descargo Enviado ADM. Old State:', ['old_state' => $oldState]);
                    if (!($user->id === $solicitud->id_solicitante || $user->hasRole('super_admin'))) {
                        Log::warning('SolicitudFondoController@update - Acceso denegado para Descargo Enviado ADM.', ['user_id' => $user->id, 'solicitante_id' => $solicitud->id_solicitante, 'user_roles' => $user->getRoleNames()]);
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el solicitante puede enviar un descargo.'], 403);
                    }
                    if ($oldState !== 'Observada ADM') {
                        Log::warning('SolicitudFondoController@update - Transición de estado inválida para Descargo Enviado ADM.', ['old_state' => $oldState, 'expected_old_state' => 'Observada ADM']);
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en estado "Observada ADM" para enviar un descargo.'], 400);
                    }
                    $solicitud->motivo_descargo = $request->motivo_descargo;
                    $historialState = 'Descargo Enviado ADM';
                    $observacionesHistorial = 'Descargo enviado por el solicitante: ' . $request->motivo_descargo . '. La solicitud vuelve a ser revisada por Administración.';
                    $newState = 'Pendiente Re-evaluacion';
                    $responseMessage = 'Descargo enviado exitosamente a Administración.'; 
                    Log::info('SolicitudFondoController@update - Descargo Enviado ADM procesado. Nuevo estado:', ['new_state' => $newState]);
                    break;

                case 'Observada GRTE':
                    if (!($user->hasRole('gerente_general') || $user->hasRole('super_admin'))) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Gerente General puede observar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación GRTE', 'Descargo Enviado GRTE'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser observada por Gerencia.'], 400);
                    }
                    $solicitud->motivo_observacion = $request->motivo_observacion;
                    $solicitud->id_aprobador_gerente = $user->id;
                    $newState = 'Observada GRTE';
                    $observacionesHistorial = 'Solicitud observada por Gerencia General: ' . $request->motivo_observacion . '. Se espera el descargo del solicitante.';
                    $responseMessage = 'Observación enviada exitosamente por Gerencia General.'; 
                    break;
                case 'Aprobada':
                    if (!($user->hasRole('gerente_general') || $user->hasRole('super_admin'))) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Gerente General puede aprobar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación GRTE', 'Descargo Enviado GRTE'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser aprobada por Gerencia.'], 400);
                    }
                    if ($isSolicitanteAdminOrSuperAdmin && $isDecrementoCierre) {
                        DB::rollBack();
                        return response()->json(['message' => 'No puedes aprobar tu propia solicitud de Decremento/Cierre. Solo el Gerente General (otro usuario) puede hacerlo.'], 403);
                    }
                    $solicitud->id_aprobador_gerente = $user->id;
                    $solicitud->motivo_observacion = null;
                    $solicitud->motivo_descargo = null;
                    $historialState = 'Aprobada';
                    $observacionesHistorial = 'Solicitud aprobada finalmente por Gerencia General. Proceso completado.';

                    $managedFondo = $this->manageFondoEfectivo($solicitud, $user);
                    $newState = 'Aprobada';
                    if ($managedFondo) {
                        $managedFondoCodigo = $managedFondo->codigo_fondo;
                        if ($solicitud->tipo_solicitud === 'Apertura') {
                            $responseMessage = "¡Éxito! Solicitud de Apertura aprobada. Fondo asignado: " . $managedFondoCodigo;
                        } elseif (in_array($solicitud->tipo_solicitud, ['Incremento', 'Decremento'])) {
                            $responseMessage = "¡Éxito! Solicitud de " . $solicitud->tipo_solicitud . " aprobada. El fondo " . $managedFondoCodigo . " ha sido actualizado.";
                        } elseif ($solicitud->tipo_solicitud === 'Cierre') {
                            $responseMessage = "¡Éxito! Solicitud de Cierre aprobada. El fondo " . $managedFondoCodigo . " ha sido cerrado.";
                        }
                    } else {
                        $responseMessage = 'Solicitud aprobada por Gerencia General exitosamente.';
                    }
                    break;
                case 'Descargo Enviado GRTE':
                    Log::info('SolicitudFondoController@update - Procesando Descargo Enviado GRTE. Old State:', ['old_state' => $oldState]);
                    if (!($user->id === $solicitud->id_solicitante || $user->hasRole('super_admin'))) {
                        Log::warning('SolicitudFondoController@update - Acceso denegado para Descargo Enviado GRTE.', ['user_id' => $user->id, 'solicitante_id' => $solicitud->id_solicitante, 'user_roles' => $user->getRoleNames()]);
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el solicitante puede enviar un descargo.'], 403);
                    }
                    if ($oldState !== 'Observada GRTE') {
                        Log::warning('SolicitudFondoController@update - Transición de estado inválida para Descargo Enviado GRTE.', ['old_state' => $oldState, 'expected_old_state' => 'Observada GRTE']);
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en estado "Observada GRTE" para enviar un descargo.'], 400);
                    }
                    $solicitud->motivo_descargo = $request->motivo_descargo;
                    $historialState = 'Descargo Enviado GRTE';
                    $observacionesHistorial = 'Descargo enviado por el solicitante: ' . $request->motivo_descargo . '. La solicitud vuelve a ser revisada por Gerencia General.';
                    $newState = 'Pendiente Aprobación GRTE';
                    $responseMessage = 'Descargo enviado exitosamente a Gerencia General.'; // Mensaje personalizado
                    Log::info('SolicitudFondoController@update - Descargo Enviado GRTE procesado. Nuevo estado:', ['new_state' => $newState]);
                    break;
                case 'Rechazada Final':
                    if (!($user->hasRole('jefe_administracion') || $user->hasRole('gerente_general') || $user->hasRole('super_admin'))) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Administración o Gerente General pueden rechazar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación ADM', 'Observada ADM', 'Descargo Enviado ADM', 'Aprobada ADM', 'Pendiente Aprobación GRTE', 'Observada GRTE', 'Descargo Enviado GRTE','Pendiente Re-evaluacion'])) {
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
                    if ($user->hasRole('jefe_administracion')) {
                        $solicitud->id_revisor_adm = $user->id;
                    } elseif ($user->hasRole('gerente_general')) {
                        $solicitud->id_aprobador_gerente = $user->id;
                    }
                    $historialState = 'Rechazada Final';
                    $observacionesHistorial = 'Solicitud rechazada finalmente: ' . $request->motivo_rechazo_final;
                    $newState = 'Rechazada Final';
                    $responseMessage = 'Solicitud rechazada definitivamente.'; // Mensaje personalizado
                    break;

                default:
                    DB::rollBack();
                    return response()->json(['message' => 'Transición de estado no válida.'], 400);
            }
            if ($solicitud->estado !== $newState) {
                $solicitud->estado = $newState;
                $solicitud->save();
                Log::info('SolicitudFondoController@update - Estado de solicitud principal actualizado en DB.', ['solicitud_id' => $solicitud->id, 'new_estado_principal' => $solicitud->estado]);
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
                    'detallesGastosProyectados',
                    'historialEstados' => function ($q) {
                        $q->orderBy('created_at', 'asc')
                            ->with('usuarioAccion:id,name,last_name');
                    },
                    'revisorAdm',
                    'aprobadorGerente',
                    'solicitante.area',
                    'solicitante.role',
                    'area',
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

        // 1. Autorización: ¿Puede el usuario editar esta solicitud ahora?
        if ($user->id !== $solicitud->id_solicitante || $solicitud->estado !== 'Pendiente Aprobación ADM') {
            return response()->json(['message' => 'Esta solicitud no puede ser editada en su estado actual.'], 403);
        }
        // 2. Validación del formulario completo
        $validatedData = $request->validate([
            'motivo_detalle' => 'required|string|max:1000',
            'monto_solicitado' => 'required|numeric|min:0',
            'prioridad' => 'required_if:tipo_solicitud,Incremento,Decremento,Cierre|in:Baja,Media,Alta,Urgente',
            'gastos_proyectados' => 'required_if:tipo_solicitud,Apertura,Incremento,Decremento|array|min:1',
            'gastos_proyectados.*.descripcion_gasto' => 'required_if:tipo_solicitud,Apertura,Incremento,Decremento|string|max:255',
            'gastos_proyectados.*.monto_estimado' => 'required_if:tipo_solicitud,Apertura,Incremento,Decremento|numeric|min:0.01',
        ]);
        $originalData = $solicitud->only(['motivo_detalle', 'monto_solicitado', 'prioridad']);
        $estadoAnterior = $solicitud->estado;
        DB::beginTransaction();
        try {
            // 3. Actualizar la solicitud principal con los datos validados.
            $solicitud->update($validatedData);
            // 4. Sincronizar los gastos proyectados.
            // Primero, eliminamos todos los gastos proyectados antiguos asociados a esta solicitud
            // para asegurar que no queden registros huérfanos.
            $solicitud->detallesGastosProyectados()->delete();
            // Luego, creamos los nuevos registros de gastos proyectados a partir de los datos
            // que vienen en la petición.
            if (!empty($validatedData['gastos_proyectados'])) {
                $solicitud->detallesGastosProyectados()->createMany($validatedData['gastos_proyectados']);
            }
            // 5. Registrar los cambios en el historial.
            // Llamamos a nuestra función de ayuda para mantener el código limpio.
            $this->trackChangesAndUpdateHistory(
                $solicitud,
                $originalData,
                $user,
                "Edición Proactiva", // Tipo de acción
                "Solicitud editada por el solicitante antes de la primera revisión.", // Detalle
                $estadoAnterior,
                $estadoAnterior
            );
            // Si todo sale bien, confirmamos los cambios en la base de datos.
            DB::commit();
            // Devolvemos una respuesta exitosa con la solicitud actualizada y sus relaciones.
            return response()->json([
                'message' => 'Solicitud actualizada con éxito.',
                'solicitud' => $solicitud->fresh()->load([
                    'solicitante.area',
                    'area',
                    'detallesGastosProyectados',
                    'historialEstados.usuarioAccion'
                ])
            ]);
        } catch (\Exception $e) {
            // Si algo sale mal, revertimos todos los cambios.
            DB::rollBack();
            // Registramos el error y devolvemos una respuesta de error.
            Log::error("Error al editar solicitud pendiente [{$solicitud->id}]: " . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar la solicitud.', 'error' => $e->getMessage()], 500);
        }
    }
    public function editarSolicitudObservada(Request $request, SolicitudFondo $solicitud)
    {
        $user = Auth::user();
        // 1. Autorización: Se mantiene la verificación.
        if ($user->id !== $solicitud->id_solicitante || !in_array($solicitud->estado, ['Observada ADM', 'Observada GRTE'])) {
            return response()->json(['message' => 'Esta solicitud no puede ser editada en su estado actual.'], 403);
        }
        // 2. Validación: Se mantiene la validación completa.
        $validatedData = $request->validate([
            'motivo_detalle' => 'required|string|max:1000',
            'monto_solicitado' => 'required|numeric|min:0',
            'prioridad' => 'required_if:tipo_solicitud,Incremento,Decremento,Cierre|in:Baja,Media,Alta,Urgente',
            'comentario_descargo' => 'nullable|string|max:1000',
            'gastos_proyectados' => 'present|array',
            'gastos_proyectados.*.descripcion_gasto' => 'required|string|max:255',
            'gastos_proyectados.*.monto_estimado' => 'required|numeric|min:0.01',
        ]);

        $originalData = $solicitud->only(['motivo_detalle', 'monto_solicitado', 'prioridad']);
        $estadoAnterior = $solicitud->estado;

        DB::beginTransaction();
        try {
            // 3. Actualizar la solicitud y sus detalles.
            $solicitud->update(Arr::except($validatedData, ['comentario_descargo', 'gastos_proyectados']));
            $solicitud->motivo_descargo = $request->comentario_descargo ?: 'Corrección de datos aplicada.';
            // Sincronizar gastos proyectados
            $solicitud->detallesGastosProyectados()->delete();
            if (!empty($validatedData['gastos_proyectados'])) {
                $solicitud->detallesGastosProyectados()->createMany($validatedData['gastos_proyectados']);
            }
            // 4. Mover la máquina de estados según quién hizo la observación.
            $nuevoEstadoPrincipal = '';
            $estadoHistorial = '';
            if ($estadoAnterior === 'Observada ADM') {
                // Si observó ADM, vuelve a ADM.
                $nuevoEstadoPrincipal = 'Pendiente Re-evaluacion';
                $estadoHistorial = 'Descargo Enviado ADM';
            } else if ($estadoAnterior === 'Observada GRTE') {
                // Si observó GRTE, vuelve directamente a GRTE.
                $nuevoEstadoPrincipal = 'Pendiente Aprobación GRTE';
                $estadoHistorial = 'Descargo Enviado GRTE';
            }
            $solicitud->estado = $nuevoEstadoPrincipal;
            // 5. Registrar la acción en el historial.
            $detalleHistorial = "Solicitud editada para subsanar observación. " . $solicitud->motivo_descargo;
            $this->trackChangesAndUpdateHistory(
                $solicitud,
                $originalData,
                $user,
                "Edición por Observación",
                $detalleHistorial,
                $estadoAnterior,
                $estadoHistorial
            );
            DB::commit();
            return response()->json([
                'message' => 'Solicitud corregida y reenviada para aprobación.',
                'solicitud' => $solicitud->fresh()->load([
                    'solicitante.area',
                    'area',
                    'detallesGastosProyectados',
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
        array $originalData,
        \App\Models\User $user,
        string $tipoAccion,
        string $detalle,
        ?string $estadoAnterior = null,
        ?string $estadoHistorial = null
    ) {
        // 1. Incrementar el contador de edición.
        $solicitud->increment('edit_count');
        // 2. CORRECCIÓN: Detectar los campos que realmente cambiaron.
        // Comparamos los datos originales solo con los campos que se actualizaron.
        $datosActualizados = $solicitud->only(array_keys($originalData));
        $cambios = array_diff_assoc($datosActualizados, $originalData);
        // 3. Guardar un registro JSON de los cambios en la propia solicitud.
        $historialCambios = $solicitud->historial_cambios ?? [];
        $historialCambios['edicion_' . $solicitud->edit_count] = [
            'usuario' => $user->name,
            'fecha' => now()->toDateTimeString(),
            'tipo' => $tipoAccion,
            'cambios' => $cambios
        ];
        $solicitud->historial_cambios = $historialCambios;
        // Guardamos los cambios en el contador y en el historial JSON.
        // Usamos saveQuietly() para no disparar otros eventos de modelo si los tuvieras.
        $solicitud->saveQuietly();
        // 4. Construir las observaciones para el historial principal.
        // Se añade la lista de campos modificados solo si hubo cambios.
        $observacionFinal = $detalle;
        if (!empty($cambios)) {
            $observacionFinal .= " Campos modificados: " . implode(', ', array_keys($cambios)) . ".";
        }
        // 5. CORRECCIÓN: Registrar la entrada en la tabla de historial con los estados correctos.
        // Se usan los parámetros explícitos para evitar ambigüedades.
        HistorialEstadoSolicitud::create([
            'id_solicitud_fondo' => $solicitud->id,
            'estado_anterior' => $estadoAnterior, // El estado real ANTES de toda la operación.
            'estado_nuevo' => $estadoHistorial, // El estado específico del evento para la bitácora.
            'observaciones' => $observacionFinal,
            'id_usuario_accion' => $user->id,
            'fecha_cambio' => now(),
        ]);
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
            // CAMBIO (Parte 2): La condición se simplifica para permitir la eliminación solo si el usuario tiene el rol 'super_admin'.
            // Esto cumple con el requisito de que ningún otro usuario (incluido el solicitante) pueda eliminar.
            if ($user->hasRole('super_admin')) {
                DB::beginTransaction();
                // Eliminar detalles de gastos proyectados asociados
                $solicitud->detallesGastosProyectados()->delete();
                // Eliminar historial de estados asociado
                $solicitud->historialEstados()->delete();
                // Eliminar la solicitud principal
                $solicitud->delete();
                DB::commit();
                return response()->json(['message' => 'Solicitud de fondo eliminada exitosamente.'], 200);
            } else {
                // Si el usuario no es un super_admin, se deniega el acceso.
                return response()->json(['message' => 'Acceso denegado. Solo un Super Administrador puede eliminar solicitudes.'], 403);
            }
        } catch (ModelNotFoundException $e) {
            DB::rollBack();
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
