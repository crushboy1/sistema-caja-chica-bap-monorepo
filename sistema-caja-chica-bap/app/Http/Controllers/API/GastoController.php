<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Gasto;
use App\Models\FondoEfectivo;
use App\Models\SolicitudFondo;
use App\Models\HistorialAprobacionGasto;
use App\Models\GastoProyectado;
use App\Rules\UniqueComprobante;
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
            'gastoProyectado:id_gasto_proyectado,descripcion',
            'djConsolidada',
            'historialAprobaciones.usuarioAccion'
        ]);

        // --- LÓGICA DE VISUALIZACIÓN POR ROL Y SCOPE ---

        // CASO 1: El frontend está solicitando la vista de "Aprobaciones".
        if ($request->input('scope') === 'aprobaciones') {
            $query->where(function ($q) use ($user) {
                // REGLA 1: Traer MIS PROPIOS gastos que están OBSERVADOS.
                // Esto aplica para Colaborador, Jefe de Área y Gerente General por igual.
                // Si yo registré un gasto y fue observado, debo verlo para corregirlo.
                $q->where('id_registrador', $user->id)
                    ->where('estado', 'Observado');
            })
                ->orWhere(function ($q) use ($user) {
                    // REGLA 2: Si soy Jefe de Área, traer los gastos de mi equipo que están PENDIENTES DE MI APROBACIÓN.
                    if ($user->hasRole('jefe_area')) {
                        $q->where('estado', 'Pendiente de Aprobación')
                            ->whereHas('registrador', function ($subQ) use ($user) {
                                // Que el registrador pertenezca a mi área.
                                $subQ->where('area_id', $user->area_id)
                                    // Y que el registrador no sea yo mismo (para no ver mis propios gastos pendientes aquí).
                                    ->where('id', '!=', $user->id);
                            });
                    } else {
                        // Si no soy Jefe de Área, esta condición no debe traer ningún resultado.
                        $q->whereRaw('1 = 0');
                    }
                });

            // CASO 2: Es cualquier otra vista (Trazabilidad, Auditoría, etc.)
        } else {
            if ($user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
                // Admin y Super Admin ven todos los gastos sin restricción.
            } elseif ($user->hasRole('jefe_area')) {
                // Un Jefe de Área ve todos los gastos registrados por personas de su área.
                $query->whereHas('registrador', function ($q) use ($user) {
                    $q->where('area_id', $user->area_id);
                });
            } else {
                // Cualquier otro rol (como Colaborador) solo ve los gastos que él mismo ha registrado.
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
        // El asterisco (*) aplica las reglas a cada elemento del array.
        $rules = [
            'id_fondo_efectivo' => ['required', 'integer', 'exists:fondo_efectivo,id_fondo'],
            'gastos' => 'required|array|min:1',
            'gastos.*.id_gasto_proyectado' => 'required|exists:gastos_proyectados,id_gasto_proyectado',
            'gastos.*.fecha_documento' => 'required|date|before_or_equal:today',
            'gastos.*.monto_total' => 'required|numeric|min:0.01',
            'gastos.*.glosa' => 'required|string|max:1000',
            'gastos.*.evidencia' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'gastos.*.es_declaracion_jurada' => 'required|boolean',
            'gastos.*.tipo_documento' => 'required_if:gastos.*.es_declaracion_jurada,false|string|max:100',
            'gastos.*.serie_documento' => 'nullable|required_if:gastos.*.es_declaracion_jurada,false|string|max:20',
            'gastos.*.correlativo_documento' => 'nullable|required_if:gastos.*.es_declaracion_jurada,false|string|max:50',
            'gastos.*.comentario' => 'nullable|string|max:2000',
            'gastos.*.moneda' => 'sometimes|in:PEN,USD',
            'gastos.*' => [new UniqueComprobante],
            'dj_consolidada_file' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ];
        $messages = [
            'required' => 'Este campo es obligatorio.',
            'gastos.*.fecha_documento.before_or_equal' => 'La fecha del documento no puede ser una fecha futura.',
            'gastos.*.monto_total.min' => 'El monto debe ser mayor que cero.',
            'gastos.*.evidencia.mimes' => 'El archivo de evidencia debe ser una imagen (jpg, png) o un PDF.',
            'gastos.*.evidencia.max' => 'El archivo de evidencia no debe superar los 10MB.',
        ];

        // 3. EJECUTAR LA VALIDACIÓN
        // 2.1. Validación de Duplicados en la rule
        $validatedData = $request->validate($rules, $messages);

        $user = Auth::user();
        $fondo = FondoEfectivo::findOrFail($validatedData['id_fondo_efectivo']);
        $gastosParaCrear = $validatedData['gastos'];
        $comprobantesEnviados = []; // Un array temporal para rastrear los comprobantes de esta solicitud
        foreach ($gastosParaCrear as $gastoData) {
            // Si no es una declaración jurada, creamos una clave única para el comprobante
            if (
                isset($gastoData['es_declaracion_jurada']) && !$gastoData['es_declaracion_jurada'] &&
                !empty($gastoData['serie_documento']) && !empty($gastoData['correlativo_documento'])
            ) {
                $claveUnica = $gastoData['tipo_documento'] . '-' . $gastoData['serie_documento'] . '-' . $gastoData['correlativo_documento'];

                // Verificamos si esta clave ya la hemos visto en esta misma solicitud
                if (isset($comprobantesEnviados[$claveUnica])) {
                    // Si ya existe, lanzamos un error de validación y detenemos el proceso
                    throw ValidationException::withMessages([
                        'gastos' => 'No puedes usar el mismo comprobante (' . $claveUnica . ') para más de un gasto en la misma declaración.'
                    ]);
                }

                // Si no la hemos visto, la añadimos a nuestro rastreador
                $comprobantesEnviados[$claveUnica] = true;
            }
        }

        // 3. TRANSACCIÓN
        // Se envuelve toda la lógica en una transacción para garantizar la integridad de los datos.
        return DB::transaction(function () use ($request, $validatedData) {
            $user = Auth::user();
            $fondo = FondoEfectivo::findOrFail($validatedData['id_fondo_efectivo']);
            $gastosParaCrear = $validatedData['gastos'];
            $gastosCreados = [];
            $djConsolidadaId = null;

            // 2.1. --- MANEJO DE LA DJ CONSOLIDADA (ANTES DEL BUCLE) ---
            if ($request->hasFile('dj_consolidada_file')) {
                $pathDj = $request->file('dj_consolidada_file')->store('djs_consolidadas', 'public');
                $djConsolidada = \App\Models\DjConsolidada::create([
                    'ruta_documento' => $pathDj,
                    'id_uploader' => $user->id,
                ]);
                $djConsolidadaId = $djConsolidada->id_dj_consolidada;
            }

            // 2.2. --- PREPARACIÓN DE DATOS (EFICIENCIA) ---
            $solicitudOriginal = SolicitudFondo::with('gastosProyectados')->find($fondo->id_solicitud_apertura);
            $montosProyectadosOriginales = $solicitudOriginal
                ? $solicitudOriginal->gastosProyectados->keyBy('id_gasto_proyectado')
                : collect();
            $idsProyectados = collect($gastosParaCrear)->pluck('id_gasto_proyectado')->unique();
            $catalogoGastos = GastoProyectado::whereIn('id_gasto_proyectado', $idsProyectados)->get()->keyBy('id_gasto_proyectado');

            // 2.3. --- BUCLE DE CREACIÓN DE GASTOS ---
            foreach ($gastosParaCrear as $index => $gastoData) {
                $pathEvidenciaIndividual = null;
                $idDjParaGasto = null;
                $esGastoConDJConsolidada = false;
                // --- Lógica condicional para asignar la evidencia correcta ---
                if (($gastoData['es_declaracion_jurada'] || $gastoData['tipo_documento'] === 'Declaración Jurada') && $djConsolidadaId) {
                    $idDjParaGasto = $djConsolidadaId;
                    $esGastoConDJConsolidada = true;
                } elseif ($request->hasFile("gastos.{$index}.evidencia")) {
                    $pathEvidenciaIndividual = $request->file("gastos.{$index}.evidencia")->store('evidencias_gastos', 'public');
                } else {
                    throw ValidationException::withMessages(["gastos.{$index}.evidencia" => 'Se requiere un archivo de evidencia para este gasto.']);
                }

                // --- Lógica de estado y aprobación inicial basada en roles ---
                $estadoInicial = 'Pendiente de Aprobación';
                $idJefeAprobador = null;
                $idValidadorAdm = null;
                $descontarSaldo = false;
                if ($user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
                    $estadoInicial = $esGastoConDJConsolidada ? 'Pendiente de Validación DJ' : 'Contabilizado';
                    $idJefeAprobador = $user->id;
                    $idValidadorAdm = $user->id;
                    $descontarSaldo = !$esGastoConDJConsolidada;
                } elseif ($user->hasAnyRole(['gerente_general', 'jefe_area'])) {
                    $estadoInicial = $esGastoConDJConsolidada ? 'Pendiente de Validación DJ' : 'Pendiente de Validación Contable';
                    $idJefeAprobador = $user->id;
                }

                // --- Obtención de datos relacionados ---
                $gastoProyectadoDelCatalogo = $catalogoGastos->get($gastoData['id_gasto_proyectado']);
                $idCuentaContable = $gastoProyectadoDelCatalogo->id_cuenta_contable;
                $montoOriginal = $montosProyectadosOriginales->has($gastoData['id_gasto_proyectado'])
                    ? $montosProyectadosOriginales->get($gastoData['id_gasto_proyectado'])->pivot->monto_estimado
                    : 0;

                // --- Creación del Gasto con la estructura final ---
                $gasto = Gasto::create(array_merge($gastoData, [
                    'id_fondo_efectivo' => $fondo->id_fondo,
                    'id_registrador' => $user->id,
                    'ruta_evidencia' => $pathEvidenciaIndividual,
                    'id_dj_consolidada' => $idDjParaGasto,
                    'estado' => $estadoInicial,
                    'id_jefe_aprobador' => $idJefeAprobador,
                    'id_validador_adm' => $idValidadorAdm,
                    'id_cuenta_contable' => $idCuentaContable,
                    'monto_proyectado_original' => $montoOriginal,
                ]));

                // --- Lógica post-creación  ---
                if ($descontarSaldo) {
                    $fondo->decrement('monto_disponible', $gasto->monto_total);
                }
                $this->registrarHistorial($gasto, 'Creado', $gasto->estado, $user->id, 'Gasto registrado en el sistema.');
                $gastosCreados[] = $gasto->load('registrador');
            }

            // 2.4. --- RESPUESTA FINAL ---
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
        $user->load('role'); // Asegurarse de que el rol esté cargado

        // 1. --- Validación de entrada ---
        $request->validate(['comentario' => 'required|string|max:2000']);
        // 2. --- Lógica de Autorización y Permisos por Rol ---
        $isJefeAreaAutorizado = ($user->role->name === 'jefe_area' && $user->area_id === $gasto->registrador->area_id);
        $isAdministrador = in_array($user->role->name, ['jefe_administracion', 'super_admin']);
        if (!$isJefeAreaAutorizado && !$isAdministrador) {
            return response()->json(['message' => 'No tienes permiso para observar este gasto.'], 403);
        }
        // 3. --- Validación de Estado según el Rol  ---
        $estadosValidosParaJefe = ['Pendiente de Aprobación'];
        // Un administrador ahora también puede observar gastos pendientes de validación de DJ.
        $estadosValidosParaAdmin = ['Pendiente de Validación Contable', 'Pendiente de Validación DJ'];
        if ($isJefeAreaAutorizado && !in_array($gasto->estado, $estadosValidosParaJefe)) {
            return response()->json(['message' => 'Como Jefe de Área, solo puedes observar gastos pendientes de tu aprobación.'], 409);
        }
        if ($isAdministrador && !in_array($gasto->estado, $estadosValidosParaAdmin)) {
            return response()->json(['message' => 'Como Administrador, este gasto no se encuentra en un estado válido para ser observado.'], 409);
        }

        // 4. --- Ejecución de la Lógica de Negocio ---
        return DB::transaction(function () use ($gasto, $user, $request, $isJefeAreaAutorizado) {
            $estadoAnterior = $gasto->estado;
            $comentarioObservacion = $request->comentario;
            $djId = $gasto->id_dj_consolidada;
            // --- LÓGICA DE INVALIDACIÓN DE GRUPO DE DJ ---
            if ($djId) {
                // 1. Obtener todos los gastos del grupo, EXCLUYENDO el que se está observando.
                $gastosHermanos = Gasto::where('id_dj_consolidada', $djId)
                    ->where('id', '!=', $gasto->id)
                    ->get();
                // 2. Romper el vínculo de la DJ y revertir el estado de los gastos hermanos.
                foreach ($gastosHermanos as $gastoHermano) {
                    $estadoPrevioHermano = $gastoHermano->estado;
                    $gastoHermano->id_dj_consolidada = null;
                    // Revertir al estado anterior a la DJ.
                    // Se asume que un colaborador los envió a 'Pendiente de Aprobación'
                    // y un jefe/admin a 'Pendiente de Validación Contable'.
                    $registradorHermano = $gastoHermano->registrador->load('role');
                    if (in_array($registradorHermano->role->name, ['jefe_area', 'gerente_general', 'jefe_administracion', 'super_admin'])) {
                        $gastoHermano->estado = 'Pendiente de Validación Contable';
                    } else {
                        $gastoHermano->estado = 'Pendiente de Aprobación';
                    }
                    $gastoHermano->save();
                    $this->registrarHistorial($gastoHermano, $estadoPrevioHermano, $gastoHermano->estado, $user->id, "DJ consolidada invalidada debido a observación en Gasto {$gasto->codigo_gasto}.");
                }
                // 3. Romper el vínculo del gasto principal que está siendo observado.
                $gasto->id_dj_consolidada = null;
            }
            // --- Lógica de Observación Individual (se ejecuta siempre) ---
            if ($gasto->id_jefe_aprobador) {
                $gasto->id_jefe_aprobador = null;
            }
            $gasto->estado = 'Observado';
            $gasto->motivo_observacion_adm = $comentarioObservacion;
            $gasto->id_observador_adm = $user->id;
            $gasto->save();

            // 5. --- Registrar en el Historial ---
            $rolObservador = $isJefeAreaAutorizado ? 'Jefe de Área' : 'Administración';
            $this->registrarHistorial(
                $gasto,
                $estadoAnterior,
                'Observado',
                $user->id,
                "Observado por {$rolObservador}: " . $comentarioObservacion
            );

            // 6. --- Respuesta Exitosa ---
            return response()->json(['message' => 'Gasto observado. El registrador será notificado para su corrección.', 'gasto' => $gasto]);
        });
    }
    //Actualizar gasto observado.
    public function actualizarGastoObservado(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        $user->load('role');

        // 1. --- Autorización ---
        // Solo el usuario que registró el gasto puede corregirlo.
        if ($user->id !== $gasto->id_registrador) {
            return response()->json(['message' => 'No tienes permiso para corregir este gasto.'], 403);
        }
        // El gasto debe estar en estado 'Observado' para poder ser corregido.
        if ($gasto->estado !== 'Observado') {
            return response()->json(['message' => 'Este gasto no se encuentra en estado de corrección.'], 409);
        }

        // 2. --- Validación de Datos ---
        // Se validan todos los campos que el usuario puede editar en el formulario de corrección.
        $validatedData = $request->validate([
            'fecha_documento' => 'required|date|before_or_equal:today',
            'tipo_documento' => 'required_if:es_declaracion_jurada,false|string|max:100',
            'serie_documento' => 'nullable|required_if:es_declaracion_jurada,false|string|max:20',
            'correlativo_documento' => 'nullable|required_if:es_declaracion_jurada,false|string|max:50',
            'monto_total' => 'required|numeric|min:0.01',
            'glosa' => 'required|string|max:1000',
            'comentario_subsanacion' => 'nullable|string|max:2000', // Campo para explicar la corrección.
            'evidencia' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240', // Evidencia puede ser opcional si no se cambia.
        ]);

        // 3. --- Ejecución de la Lógica de Negocio ---
        return DB::transaction(function () use ($gasto, $user, $request, $validatedData) {
            $estadoAnterior = $gasto->estado; // Guardamos 'Observado' para el historial.

            // 3.1. Manejo del archivo de evidencia si se sube uno nuevo.
            if ($gasto->id_dj_consolidada) {
                $gasto->id_dj_consolidada = null;
            }

            // Manejo del archivo de evidencia individual si se sube uno nuevo.
            if ($request->hasFile('evidencia')) {
                if ($gasto->ruta_evidencia) {
                    Storage::disk('public')->delete($gasto->ruta_evidencia);
                }
                $validatedData['ruta_evidencia'] = $request->file('evidencia')->store('evidencias_gastos', 'public');
            }
            // Capturamos los datos originales ANTES de la actualización
            $datosOriginales = $gasto->only(['monto_total', 'glosa', 'fecha_documento']);
            // Almacenamos los cambios para el historial
            $cambiosParaHistorial = [];
            // 3.2. Actualizar el gasto con los datos validados.
            $gasto->update($validatedData);
            // Comparamos cada campo para registrar los cambios
            foreach ($datosOriginales as $campo => $valorOriginal) {
                if ($gasto->{$campo} != $valorOriginal) {
                    $cambiosParaHistorial[$campo] = [
                        'anterior' => $valorOriginal,
                        'nuevo' => $gasto->{$campo},
                    ];
                }
            }
            // 3.3. Determinar el nuevo estado para reiniciar el flujo.
            $rolRegistrador = $gasto->registrador->role->name;
            $nuevoEstado = 'Pendiente de Aprobación'; // Por defecto, para colaboradores.

            // Si quien registró fue un Jefe o Gerente, el gasto pasa directamente a validación contable.
            if (in_array($rolRegistrador, ['jefe_area', 'gerente_general', 'jefe_administracion', 'super_admin'])) {
                $nuevoEstado = 'Pendiente de Validación Contable';
                // Se asigna al jefe como su propio aprobador de primer nivel.
                $gasto->id_jefe_aprobador = $user->id;
            }

            $gasto->estado = $nuevoEstado;

            // 3.4. Limpiar los campos de la observación anterior para el nuevo ciclo.
            $gasto->motivo_observacion_adm = null;
            $gasto->id_observador_adm = null;

            $gasto->save();

            // 4. --- Registrar en el Historial ---
            $this->registrarHistorial(
                $gasto,
                $estadoAnterior,
                $nuevoEstado,
                $user->id,
                "Gasto corregido por el usuario. " . ($request->comentario_subsanacion ?? 'Sin comentarios adicionales.'),
                $cambiosParaHistorial
            );

            // 5. --- Respuesta Exitosa ---
            return response()->json([
                'message' => 'Gasto corregido y reenviado para aprobación.',
                'gasto' => $gasto->load(['registrador.role', 'jefeAprobador']) // Devolver el gasto actualizado
            ]);
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
    private function registrarHistorial(Gasto $gasto, string $estadoAnterior, string $estadoNuevo, int $userId, ?string $comentario, array $cambios = null)
    {
        HistorialAprobacionGasto::create([
            'id_gasto' => $gasto->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'id_usuario_accion' => $userId,
            'comentario' => $comentario,
            'cambios_realizados' => $cambios ? json_encode($cambios) : null,
            'fecha_cambio' => now(),
        ]);
    }

    /**
     * Muestra un gasto específico.
     */
    public function show(Gasto $gasto)
    {
        return response()->json($gasto->load(['registrador.role', 'registrador.area', 'jefeAprobador', 'validadorAdm', 'cuentaContable', 'gastoProyectado', 'historial.usuarioAccion']));
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
