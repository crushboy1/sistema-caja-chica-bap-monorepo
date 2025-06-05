<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SolicitudFondo;
use App\Models\DetalleGastoProyectado;
use App\Models\FondoEfectivo; // Necesario para gestionar fondos activos
use App\Models\HistorialEstadoSolicitud; // Para registrar cambios de estado
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB; // Para transacciones de base de datos
use Illuminate\Support\Str; // Para funciones de cadena, útil en la generación de códigos
use Illuminate\Support\Facades\Log;

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

        $solicitudes = $query->orderBy('created_at', 'desc')->get();

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
        // CAMBIO 1: Se ajusta la validación de gastos_proyectados para no requerirlos en solicitudes de Cierre.
        // Se mantiene la validación para 'Apertura', 'Incremento', 'Decremento'.
        $request->validate([
            'tipo_solicitud' => 'required|in:Apertura,Incremento,Decremento,Cierre',
            'motivo_detalle' => 'required|string|max:1000',
            'monto_solicitado' => 'required|numeric|min:0', // Ahora es el NUEVO MONTO TOTAL DESEADO
            'prioridad' => 'required|in:Baja,Media,Alta,Urgente',
            'id_area' => 'required|exists:areas,id',
            'id_solicitud_original' => 'nullable|exists:solicitudes_fondos,id', // Requerido para Incremento/Decremento/Cierre

            // Validaciones condicionales para gastos proyectados
            // CAMBIO 2: 'required_if' ajustado para NO incluir 'Cierre'
            'gastos_proyectados' => 'required_if:tipo_solicitud,Apertura,Incremento,Decremento|array|min:1',
            'gastos_proyectados.*.descripcion_gasto' => 'required_if:tipo_solicitud,Apertura,Incremento,Decremento|string|max:255',
            'gastos_proyectados.*.monto_estimado' => 'required_if:tipo_solicitud,Apertura,Incremento,Decremento|numeric|min:0.01',
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
                // CAMBIO 3: Asegurar que el monto solicitado sea exactamente 0 para un cierre.
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

            // Generar el codigo_solicitud de forma secuencial y con formato
            $newCodigoSolicitud = $this->generateUniqueCode($request->tipo_solicitud);

            // --- Lógica para determinar el estado inicial principal de la solicitud ---
            // CAMBIO: Lógica ajustada para la Parte 2:
            // - Apertura/Incremento siempre inician en 'Pendiente Aprobación ADM'.
            // - Decremento/Cierre de Jefe de Área/Colaborador inician en 'Pendiente Aprobación ADM'.
            // - Decremento/Cierre de Jefe de Administración/Super Admin inician en 'Pendiente Aprobación GRTE'.
            $initialStateInDB = 'Pendiente Aprobación ADM'; 
            $initialHistorialObservation = 'enviada a Administración'; // Observación inicial para historial

            // Si es una solicitud de Decremento o Cierre Y el solicitante es Jefe de Administración o Super Admin
            if (in_array($request->tipo_solicitud, ['Decremento', 'Cierre']) && 
                ($user->hasRole('jefe_administracion') || $user->hasRole('super_admin'))) {
                $initialStateInDB = 'Pendiente Aprobación GRTE'; // Va directo a Gerente General
                $initialHistorialObservation = 'enviada directamente a Gerencia General';
            }
            // NOTA: 'Creada' es un estado solo para el historial y no se persiste en la columna 'estado' de la tabla SolicitudFondo.

            // Crear la solicitud de fondo con el estado principal gestionable
            $solicitud = SolicitudFondo::create([
                'codigo_solicitud' => $newCodigoSolicitud,
                'id_solicitante' => $user->id, // El usuario autenticado es el solicitante
                'id_area' => $request->id_area,
                'tipo_solicitud' => $request->tipo_solicitud,
                'motivo_detalle' => $request->motivo_detalle,
                'monto_solicitado' => $request->monto_solicitado, // AHORA ES EL NUEVO MONTO TOTAL DESEADO
                'prioridad' => $request->prioridad,
                'estado' => $initialStateInDB, // Usar el estado inicial principal determinado
                'id_solicitud_original' => $request->id_solicitud_original,
            ]);

            // Guardar los detalles de gastos proyectados (solo si se proporcionan y son relevantes para el tipo de solicitud)
            // CAMBIO 4: Lógica para no guardar gastos proyectados si es tipo Cierre
            if ($request->has('gastos_proyectados') && in_array($request->tipo_solicitud, ['Apertura', 'Incremento', 'Decremento'])) {
                foreach ($request->gastos_proyectados as $gastoProyectadoData) {
                    $solicitud->detallesGastosProyectados()->create([
                        'descripcion_gasto' => $gastoProyectadoData['descripcion_gasto'],
                        'monto_estimado' => $gastoProyectadoData['monto_estimado'],
                    ]);
                }
            }

            // --- Parte 1: Registro del estado 'Creada' en el historial ---
            HistorialEstadoSolicitud::create([
                'id_solicitud_fondo' => $solicitud->id,
                'estado_anterior' => null,
                'estado_nuevo' => 'Creada', // Primer estado en el historial para trazabilidad
                'observaciones' => 'La solicitud de ' . $request->tipo_solicitud . ' fue creada por ' . $user->name . ' ' . $user->last_name . '.',
                'id_usuario_accion' => $user->id,
                'fecha_cambio' => $solicitud->created_at, // Usar la fecha de creación de la solicitud
            ]);

            // --- Parte 1: Registro del primer estado gestionable en el historial ---
            HistorialEstadoSolicitud::create([
                'id_solicitud_fondo' => $solicitud->id,
                'estado_anterior' => 'Creada', // El estado anterior para esta entrada es 'Creada'
                'estado_nuevo' => $initialStateInDB, // Registrar el primer estado gestionable
                'observaciones' => 'La solicitud fue ' . $initialHistorialObservation . ' para revisión.',
                'id_usuario_accion' => $user->id,
                'fecha_cambio' => now(),
            ]);

            DB::commit(); // Confirmar la transacción

            // CAMBIO 5: Devolver la solicitud con su código_solicitud en la respuesta del store.
            // Es crucial que el frontend reciba este dato inmediatamente.
            return response()->json([
                'message' => 'Solicitud de fondo creada exitosamente.',
                'codigo_solicitud' => $solicitud->codigo_solicitud, // <<-- ¡AQUÍ ESTÁ LA CLAVE!
                'solicitud' => $solicitud->load([
                    'solicitante.area',
                    'solicitante.role',
                    'area',
                    'detallesGastosProyectados',
                    // Cargar solicitudOriginal con todos los campos necesarios
                    'solicitudOriginal:id,codigo_solicitud,id_solicitante,id_area,tipo_solicitud,motivo_detalle,monto_solicitado,prioridad,estado,motivo_observacion,motivo_descargo,motivo_rechazo_final,id_revisor_adm,id_aprobador_gerente,id_solicitud_original,created_at,updated_at',
                    // Cargar fondoEfectivo de la solicitudOriginal con sus relaciones anidadas
                    'solicitudOriginal.fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
                    'solicitudOriginal.fondoEfectivo.responsable:id,name,last_name,email,cargo',
                    'solicitudOriginal.fondoEfectivo.area:id,name',
                    // También cargar fondoEfectivo directo (para solicitudes de Apertura)
                    'fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
                    'fondoEfectivo.responsable:id,name,last_name,email,cargo',
                    'fondoEfectivo.area:id,name',
                    // Asegurar que la relación 'usuarioAccion' se carga para 'historialEstados'
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
                'aprobadorGerente:id,name,last_name',
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
     *
     * @param SolicitudFondo $solicitud
     * @param mixed $user
     * @throws \Exception
     */
    private function manageFondoEfectivo(SolicitudFondo $solicitud, $user)
    {
        try {
            if ($solicitud->tipo_solicitud === 'Apertura') {
                // Busca si ya existe un fondo para esta solicitud de apertura, si no, lo crea.
                // Esto previene duplicados si la función se llama varias veces por error.
                // CAMBIO 6: Usar generateUniqueFondoCode para generar el código de fondo de forma robusta.
                $fondoEfectivo = FondoEfectivo::firstOrCreate(
                    ['id_solicitud_apertura' => $solicitud->id],
                    [
                        'codigo_fondo' => $this->generateUniqueFondoCode(), // Genera un código de fondo único
                        'id_responsable' => $solicitud->id_solicitante,
                        'id_area' => $solicitud->id_area,
                        'monto_aprobado' => $solicitud->monto_solicitado,
                        'fecha_apertura' => now()->toDateString(),
                        'estado' => 'Activo',
                    ]
                );
            } elseif (in_array($solicitud->tipo_solicitud, ['Incremento', 'Decremento', 'Cierre'])) {
                // Para incremento/decremento/cierre, se modifica el fondo original asociado a la solicitud de apertura
                $fondoOriginal = FondoEfectivo::where('id_solicitud_apertura', $solicitud->id_solicitud_original)->firstOrFail();

                if ($solicitud->tipo_solicitud === 'Incremento' || $solicitud->tipo_solicitud === 'Decremento') {
                    // El monto_solicitado de la solicitud de modificación es el NUEVO MONTO TOTAL deseado para el fondo.
                    $fondoOriginal->monto_aprobado = $solicitud->monto_solicitado;
                    // 'monto_disponible' ya no se actualiza aquí, ya que no se trabaja con él según el contexto.
                    // $fondoOriginal->monto_disponible = $solicitud->monto_solicitado; 
                } elseif ($solicitud->tipo_solicitud === 'Cierre') {
                    $fondoOriginal->estado = 'Cerrado';
                    $fondoOriginal->fecha_cierre = now()->toDateString();
                    $fondoOriginal->motivo_cierre = $solicitud->motivo_detalle; // Usar el motivo de la solicitud de cierre
                    // CAMBIO 7: Cuando un fondo se cierra, su monto aprobado debe ser 0.
                    $fondoOriginal->monto_aprobado = 0.00;
                }
                $fondoOriginal->save();
            }
        } catch (ModelNotFoundException $e) {
            Log::error('FondoEfectivo no encontrado para la solicitud de tipo modificación: ' . $solicitud->id, ['error' => $e->getMessage()]);
            throw new \Exception('No se pudo encontrar el fondo de efectivo original para actualizar.');
        } catch (\Exception $e) {
            Log::error('Error al gestionar FondoEfectivo para solicitud: ' . $solicitud->id, ['error' => $e->getMessage()]);
            throw new \Exception('Error interno al gestionar el fondo de efectivo.');
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
                'estado' => 'required|in:Observada ADM,Aprobada ADM,Descargo Enviado ADM,Observada GRTE,Aprobada,Rechazada Final,Descargo Enviado GRTE',
                'motivo_observacion' => 'required_if:estado,Observada ADM,Observada GRTE|string|max:1000',
                'motivo_descargo' => 'required_if:estado,Descargo Enviado ADM,Descargo Enviado GRTE|string|max:1000',
                'motivo_rechazo_final' => 'required_if:estado,Rechazada Final|string|max:1000',
            ]);
            Log::info('SolicitudFondoController@update - Validación de request exitosa.');


            $newState = $oldState; // Inicialmente, el nuevo estado principal es el mismo que el anterior, cambiará en el switch
            $historialState = $requestedState; // Por defecto, el estado a registrar en historial es el solicitado
            $observacionesHistorial = ''; // La variable observacionesHistorial ahora se construirá de forma dinámica
            
            // CAMBIO (Parte 2): Lógica para restricciones de auto-aprobación para Jefe de Administración/Super Admin en Decremento/Cierre
            $isSolicitanteAdminOrSuperAdmin = ($solicitud->id_solicitante === $user->id && 
                                                ($user->hasRole('jefe_administracion') || $user->hasRole('super_admin')));
            $isDecrementoCierre = in_array($solicitud->tipo_solicitud, ['Decremento', 'Cierre']);


            DB::beginTransaction(); // Iniciar transacción para el update
            switch ($requestedState) { // Usar requestedState para el switch para la lógica de permisos/transiciones
                case 'Observada ADM':
                    if (!($user->hasRole('jefe_administracion') || $user->hasRole('super_admin'))) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Administración puede observar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación ADM', 'Descargo Enviado ADM'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser observada por Administración.'], 400);
                    }
                    // CAMBIO (Parte 2): Restricción de Observar para Jefe ADM/Super Admin que son solicitantes de Decremento/Cierre
                    if ($isSolicitanteAdminOrSuperAdmin && $isDecrementoCierre) {
                           DB::rollBack();
                           return response()->json(['message' => 'No puedes observar tu propia solicitud de Decremento/Cierre. Solo el Gerente General puede hacerlo.'], 403);
                    }
                    $solicitud->motivo_observacion = $request->motivo_observacion;
                    $solicitud->id_revisor_adm = $user->id;
                    $newState = 'Observada ADM'; // El estado principal de la solicitud cambia a Observada ADM
                    $observacionesHistorial = 'Solicitud observada por Administración: ' . $request->motivo_observacion;
                    break;

                case 'Aprobada ADM':
                    if (!($user->hasRole('jefe_administracion') || $user->hasRole('super_admin'))) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Administración puede aprobar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación ADM', 'Descargo Enviado ADM'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser aprobada por Administración.'], 400);
                    }
                    // CAMBIO (Parte 2): Restricción de Aprobar para Jefe ADM/Super Admin que son solicitantes de Decremento/Cierre
                    if ($isSolicitanteAdminOrSuperAdmin && $isDecrementoCierre) {
                        DB::rollBack();
                        return response()->json(['message' => 'No puedes aprobar tu propia solicitud de Decremento/Cierre. Solo el Gerente General puede hacerlo.'], 403);
                    }

                    $solicitud->id_revisor_adm = $user->id;
                    $solicitud->motivo_observacion = null; // Limpiar observación previa ADM
                    $solicitud->motivo_descargo = null; // Limpiar descargo previo ADM
                    $historialState = 'Aprobada ADM'; // Hito para el historial

                    // CAMBIO (Parte 2): Nueva lógica para determinar el siguiente estado principal ($newState)
                    // Solicitudes de Apertura o Incremento SIEMPRE pasan a GRTE.
                    // Solicitudes de Decremento/Cierre de un Jefe de Área/Colaborador son APROBACIÓN FINAL por ADM.
                    // Solicitudes de Decremento/Cierre de un Jefe ADM/Super Admin SIEMPRE pasan a GRTE.
                    if (in_array($solicitud->tipo_solicitud, ['Apertura', 'Incremento']) || 
                        ($isDecrementoCierre && $solicitud->solicitante && ($solicitud->solicitante->hasRole('jefe_administracion') || $solicitud->solicitante->hasRole('super_admin')))) {
                        
                        $newState = 'Pendiente Aprobación GRTE';
                        $observacionesHistorial = 'Solicitud aprobada por Administración. Pasa a pendiente de aprobación de Gerencia General.';
                    } else if (in_array($solicitud->tipo_solicitud, ['Decremento', 'Cierre']) && 
                               $solicitud->solicitante && ($solicitud->solicitante->hasRole('jefe_area') || $solicitud->solicitante->hasRole('colaborador'))) {
                        
                        $newState = 'Aprobada'; // Aprobación final por ADM
                        $solicitud->id_aprobador_gerente = $user->id; // ADM actúa como aprobador final
                        $observacionesHistorial = 'Solicitud de ' . $solicitud->tipo_solicitud . ' aprobada finalmente por Administración.';
                    } else {
                           // Fallback, aunque con la lógica actual no debería llegar aquí
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
                    $historialState = 'Descargo Enviado ADM'; // Hito para el historial
                    $observacionesHistorial = 'Descargo enviado por el solicitante: ' . $request->motivo_descargo . '. La solicitud vuelve a ser revisada por Administración.';
                    $newState = 'Pendiente Aprobación ADM'; // El estado principal de la solicitud vuelve a este estado
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
                    // CAMBIO (Parte 2): Restricción de Observar para Jefe ADM/Super Admin que son solicitantes de Decremento/Cierre
                    if ($isSolicitanteAdminOrSuperAdmin && $isDecrementoCierre) {
                           // El gerente general sí puede observar la solicitud de un ADM/Super Admin.
                           // No hay restricción aquí para el GRTE.
                    }
                    $solicitud->motivo_observacion = $request->motivo_observacion;
                    $solicitud->id_aprobador_gerente = $user->id;
                    $newState = 'Observada GRTE'; // El estado principal de la solicitud cambia a Observada GRTE
                    $observacionesHistorial = 'Solicitud observada por Gerencia General: ' . $request->motivo_observacion . '. Se espera el descargo del solicitante.';
                    break;

                case 'Aprobada': // Aprobación final por Gerente General
                    if (!($user->hasRole('gerente_general') || $user->hasRole('super_admin'))) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Gerente General puede aprobar solicitudes.'], 403);
                    }
                    if (!in_array($oldState, ['Pendiente Aprobación GRTE', 'Descargo Enviado GRTE'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser aprobada por Gerencia.'], 400);
                    }
                    // CAMBIO (Parte 2): Restricción de Aprobar para Jefe ADM/Super Admin que son solicitantes de Decremento/Cierre
                    if ($isSolicitanteAdminOrSuperAdmin && $isDecrementoCierre) {
                           DB::rollBack();
                           return response()->json(['message' => 'No puedes aprobar tu propia solicitud de Decremento/Cierre. Solo el Gerente General (otro usuario) puede hacerlo.'], 403);
                    }
                    $solicitud->id_aprobador_gerente = $user->id;
                    $solicitud->motivo_observacion = null; // Limpiar observación previa GRTE
                    $solicitud->motivo_descargo = null; // Limpiar descargo previo GRTE
                    $historialState = 'Aprobada'; // Hito para el historial (Aprobación Final)
                    $observacionesHistorial = 'Solicitud aprobada finalmente por Gerencia General. Proceso completado.';

                    // Gestionar el fondo efectivo (crear/actualizar) una vez la solicitud es aprobada finalmente
                    $this->manageFondoEfectivo($solicitud, $user);
                    $newState = 'Aprobada'; // El estado principal de la solicitud es Aprobada
                    break;

                case 'Descargo Enviado GRTE':
                    Log::info('SolicitudFondoController@update - Procesando Descargo Enviado GRTE. Old State:', ['old_state' => $oldState]);
                    // CAMBIO (Parte 2): Aclaración de permisos. Solo el solicitante (incluido Jefe ADM/Super Admin)
                    // o un super_admin pueden enviar un descargo.
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
                    $historialState = 'Descargo Enviado GRTE'; // Hito para el historial
                    $observacionesHistorial = 'Descargo enviado por el solicitante: ' . $request->motivo_descargo . '. La solicitud vuelve a ser revisada por Gerencia General.';
                    $newState = 'Pendiente Aprobación GRTE'; // El estado principal de la solicitud vuelve a este estado
                    Log::info('SolicitudFondoController@update - Descargo Enviado GRTE procesado. Nuevo estado:', ['new_state' => $newState]);
                    break;

                case 'Rechazada Final':
                    if (!($user->hasRole('jefe_administracion') || $user->hasRole('gerente_general') || $user->hasRole('super_admin'))) {
                        DB::rollBack();
                        return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Administración o Gerente General pueden rechazar solicitudes.'], 403);
                    }
                    // Cualquier estado previo al rechazo final es válido para ser rechazado
                    if (!in_array($oldState, ['Pendiente Aprobación ADM', 'Observada ADM', 'Descargo Enviado ADM', 'Aprobada ADM', 'Pendiente Aprobación GRTE', 'Observada GRTE', 'Descargo Enviado GRTE'])) {
                        DB::rollBack();
                        return response()->json(['message' => 'La solicitud no está en un estado que permita ser rechazada finalmente.'], 400);
                    }
                    // CAMBIO (Parte 2): Restricción de Rechazar para Jefe ADM/Super Admin que son solicitantes de Decremento/Cierre
                    if ($isSolicitanteAdminOrSuperAdmin && $isDecrementoCierre) {
                           DB::rollBack();
                           return response()->json(['message' => 'No puedes rechazar tu propia solicitud de Decremento/Cierre. Solo el Gerente General puede hacerlo.'], 403);
                    }

                    if (empty($request->motivo_rechazo_final)) {
                        throw ValidationException::withMessages(['motivo_rechazo_final' => 'El motivo del rechazo final es obligatorio.']);
                    }
                    $solicitud->motivo_rechazo_final = $request->motivo_rechazo_final;
                    // Registrar quién realizó el rechazo final
                    if ($user->hasRole('jefe_administracion')) {
                        $solicitud->id_revisor_adm = $user->id;
                    } elseif ($user->hasRole('gerente_general')) {
                        $solicitud->id_aprobador_gerente = $user->id;
                    }
                    $historialState = 'Rechazada Final'; // Hito para el historial
                    $observacionesHistorial = 'Solicitud rechazada finalmente: ' . $request->motivo_rechazo_final;
                    $newState = 'Rechazada Final'; // El estado principal de la solicitud es Rechazada Final
                    break;

                default:
                    DB::rollBack();
                    return response()->json(['message' => 'Transición de estado no válida.'], 400);
            }

            // Actualizar el estado de la solicitud en la base de datos (columna 'estado')
            // Esto solo se hace si el estado principal ($newState) realmente cambió.
            if ($solicitud->estado !== $newState) {
                $solicitud->estado = $newState;
                $solicitud->save();
                Log::info('SolicitudFondoController@update - Estado de solicitud principal actualizado en DB.', ['solicitud_id' => $solicitud->id, 'new_estado_principal' => $solicitud->estado]);
            }


            // Registrar la acción/hitos en el historial de estados
            // CAMBIO (Parte 1): Se usa $historialState para la entrada del historial.
            // La observación es la que se construyó en el switch.
            HistorialEstadoSolicitud::create([
                'id_solicitud_fondo' => $solicitud->id,
                'estado_anterior' => $oldState,
                'estado_nuevo' => $historialState, // El estado que representa la acción/hito en el historial
                'observaciones' => $observacionesHistorial, 
                'id_usuario_accion' => $user->id,
                'fecha_cambio' => now(),
            ]);
            Log::info('SolicitudFondoController@update - Historial de estado registrado. Estado registrado: ' . $historialState);

            DB::commit(); // Confirmar la transacción
            Log::info('SolicitudFondoController@update - Transacción de DB confirmada exitosamente.');


            return response()->json([
                'message' => 'Solicitud de fondo actualizada exitosamente.',
                // Cargar todas las relaciones necesarias para la respuesta
                'solicitud' => $solicitud->load([
                    'detallesGastosProyectados',
                    // Asegurar que la relación 'usuarioAccion' se carga para 'historialEstados'
                    'historialEstados' => function ($q) {
                        $q->orderBy('created_at', 'asc')
                            ->with('usuarioAccion:id,name,last_name');
                    },
                    'revisorAdm',
                    'aprobadorGerente',
                    'solicitante.area',
                    'solicitante.role',
                    'area',
                    // Cargar solicitudOriginal con todos los campos necesarios
                    'solicitudOriginal:id,codigo_solicitud,id_solicitante,id_area,tipo_solicitud,motivo_detalle,monto_solicitado,prioridad,estado,motivo_observacion,motivo_descargo,motivo_rechazo_final,id_revisor_adm,id_aprobador_gerente,id_solicitud_original,created_at,updated_at',
                    // Cargar fondoEfectivo de la solicitudOriginal con sus relaciones anidadas
                    'solicitudOriginal.fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado,estado,id_responsable,id_area,id_solicitud_apertura',
                    'solicitudOriginal.fondoEfectivo.responsable:id,name,last_name,email,cargo',
                    'solicitudOriginal.fondoEfectivo.area:id,name',
                    // También cargar fondoEfectivo directo (para solicitudes de Apertura)
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

    /**
     * Genera un código único secuencial para las solicitudes de fondo.
     * El formato es 'SOL-NNNNN'.
     *
     * @param string $tipo_solicitud (No se utiliza directamente en la generación del código, pero puede ser útil para contexto futuro).
     * @return string
     */
    private function generateUniqueCode(string $tipo_solicitud): string
    {
        // El prefijo general para todas las solicitudes será 'SOL'
        $fullPrefix = 'SOL';

        // Buscar la última solicitud que comience con el prefijo 'SOL-'
        $latestSolicitud = SolicitudFondo::where('codigo_solicitud', 'like', $fullPrefix . '-%')
                                        ->latest('id') // Ordenar por ID para obtener la más reciente en caso de códigos no secuenciales por error
                                        ->first();

        $nextNumber = 1;
        if ($latestSolicitud) {
            $lastCode = $latestSolicitud->codigo_solicitud;
            // Asegurar que $lastCode es una cadena no vacía antes de intentar la expresión regular
            if (!empty($lastCode) && is_string($lastCode)) {
                // preg_match devuelve 1 si hay coincidencia, 0 si no hay, y false si hay un error
                if (preg_match('/-(\d+)$/', $lastCode, $matches) === 1) {
                    // Si se encuentra una coincidencia, $matches[1] contendrá los dígitos capturados
                    $nextNumber = (int)$matches[1] + 1;
                }
                // Si no hay coincidencia (0) o error (false), $nextNumber permanece en 1, que es el valor por defecto.
            } else {
                // Registrar una advertencia si el codigo_solicitud no es válido, pero continuar con nextNumber = 1
                Log::warning('El codigo_solicitud de la última solicitud no es una cadena válida o está vacío. ID: ' . $latestSolicitud->id . ' Codigo: ' . ($lastCode ?? 'NULL'));
            }
        }

        // Formato final: SOL-NÚMERO_PADDEADO (ej. SOL-00001)
        return $fullPrefix . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Genera un código único secuencial para los fondos de efectivo.
     * El formato es 'FNRO-NNNNN'.
     *
     * @return string
     */
    private function generateUniqueFondoCode(): string
    {
        $prefix = 'FNRO';
        $latestFondo = FondoEfectivo::where('codigo_fondo', 'like', $prefix . '-%')
                                    ->latest('id_fondo') // Asumiendo que 'id_fondo' es autoincremental
                                    ->first();

        $nextNumber = 1;
        if ($latestFondo) {
            $lastCode = $latestFondo->codigo_fondo;
            if (preg_match('/-(\d+)$/', $lastCode, $matches)) {
                $nextNumber = (int)$matches[1] + 1;
            }
        }

        return $prefix . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }
}
