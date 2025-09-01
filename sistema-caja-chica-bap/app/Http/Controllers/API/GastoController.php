<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Gasto;
use App\Models\FondoEfectivo;
use App\Models\SolicitudFondo;
use App\Models\HistorialAprobacionGasto;
use App\Models\GastoProyectado;
use App\Models\DjConsolidada;
use App\Models\TipoDocumentoComprobante;
use App\Models\Area;
use App\Models\CentroCosto;
use App\Rules\UniqueComprobante;
use App\Traits\RegistersHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use App\Exports\GastosReportExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;


/**
 * GastoController se encarga de todo el ciclo de vida de las declaraciones de gastos.
 * Esta versión refactorizada utiliza endpoints de acción específicos (approve, observe, etc.)
 * para una API más clara, segura y mantenible, eliminando el "Fat Controller".
 */
class GastoController extends Controller
{
    use RegistersHistory;
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
            'historialAprobaciones.usuarioAccion',
            'tipoDocumento'
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
        $rules = [
            'id_fondo_efectivo' => ['required', 'integer', 'exists:fondo_efectivo,id_fondo'],
            'gastos' => 'required|array|min:1',
            'gastos.*.id_gasto_proyectado' => 'required|exists:gastos_proyectados,id_gasto_proyectado',
            'gastos.*.fecha_documento' => 'required|date|before_or_equal:today',
            'gastos.*.monto_total' => 'required|numeric|min:0.01',
            'gastos.*.glosa' => 'required|string|max:1000',
            'gastos.*.evidencia' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'gastos.*.es_declaracion_jurada' => 'required|boolean',
            'gastos.*.id_tipo_documento_comprobante' => 'nullable|exists:tipos_documento_comprobante,id',
            'gastos.*.serie_documento' => 'nullable|string|max:20',
            'gastos.*.correlativo_documento' => 'nullable|string|max:50',
            'gastos.*.ruc_proveedor' => 'nullable|string|max:11|regex:/^[0-9]*$/',
            'gastos.*.comentario' => 'nullable|string|max:2000',
            'gastos.*.moneda' => 'sometimes|in:PEN,USD',
            'gastos.*' => [new UniqueComprobante],
            'dj_consolidada_file' => 'nullable|file|mimes:pdf|max:10240',
        ];
        $messages = [
            'required' => 'Este campo es obligatorio.',
            'gastos.*.fecha_documento.before_or_equal' => 'La fecha del documento no puede ser una fecha futura.',
            'gastos.*.monto_total.min' => 'El monto debe ser mayor que cero.',
            'gastos.*.evidencia.mimes' => 'El archivo de evidencia debe ser una imagen (jpg, png) o un PDF.',
            'gastos.*.evidencia.max' => 'El archivo de evidencia no debe superar los 10MB.',
        ];

        // 2. EJECUTAR LA VALIDACIÓN BÁSICA
        $validatedData = $request->validate($rules, $messages);

        // 3. VALIDACIÓN PERSONALIZADA PARA DECLARACIONES JUradas
        $gastosParaCrear = $validatedData['gastos'];
        $comprobantesEnviados = []; // Un array temporal para rastrear los comprobantes de esta solicitud
        
        foreach ($gastosParaCrear as $index => $gastoData) {
            // Validar campos requeridos según el tipo de gasto
            if (!$gastoData['es_declaracion_jurada']) {
                // Para gastos que NO son declaración jurada
                if (empty($gastoData['id_tipo_documento_comprobante'])) {
                    throw ValidationException::withMessages([
                        "gastos.{$index}.id_tipo_documento_comprobante" => 'El tipo de documento es obligatorio para gastos que no son declaración jurada.'
                    ]);
                }
                
                if (empty($gastoData['serie_documento'])) {
                    throw ValidationException::withMessages([
                        "gastos.{$index}.serie_documento" => 'La serie del documento es obligatoria para gastos que no son declaración jurada.'
                    ]);
                }
                
                if (empty($gastoData['correlativo_documento'])) {
                    throw ValidationException::withMessages([
                        "gastos.{$index}.correlativo_documento" => 'El correlativo del documento es obligatorio para gastos que no son declaración jurada.'
                    ]);
                }
                
                // Validar duplicados de comprobantes
                $claveUnica = $gastoData['id_tipo_documento_comprobante'] . '-' . $gastoData['serie_documento'] . '-' . $gastoData['correlativo_documento'];

                if (isset($comprobantesEnviados[$claveUnica])) {
                    throw ValidationException::withMessages([
                        'gastos' => 'No puedes usar el mismo comprobante (' . $claveUnica . ') para más de un gasto en la misma declaración.'
                    ]);
                }
                $comprobantesEnviados[$claveUnica] = true;
            }
        }

            $user = Auth::user();
            $fondo = FondoEfectivo::findOrFail($validatedData['id_fondo_efectivo']);
            $gastosParaCrear = $validatedData['gastos'];
            $gastosCreados = [];
            $djConsolidadaId = null;

            // 2.1. --- MANEJO DE LA DJ CONSOLIDADA (ANTES DEL BUCLE) ---
            $tieneGastosDJ = collect($gastosParaCrear)->contains('es_declaracion_jurada', true);

            if ($tieneGastosDJ) {
                // Calculamos el monto total solo de los gastos que son parte de esta DJ.
                $montoTotalDJ = collect($gastosParaCrear)
                    ->where('es_declaracion_jurada', true)
                    ->sum('monto_total');

                $pathDjFirmado = null;
                $idUploaderFirmado = null;

                // Si se adjunta el archivo firmado en el momento, lo guardamos.
                if ($request->hasFile('dj_consolidada_file')) {
                    $pathDjFirmado = $request->file('dj_consolidada_file')->store('djs_consolidadas', 'public');
                    $idUploaderFirmado = $user->id;
                }

                $djConsolidada = DjConsolidada::create([
                    'fondo_efectivo_id' => $fondo->id_fondo,
                    'fecha_declaracion' => now(),
                    'monto_total_declarado' => $montoTotalDJ,
                    'estado' => 'Declarado', // Estado inicial
                    'creado_por' => $user->id, // El usuario que declara
                    'ruta_documento_firmado' => $pathDjFirmado,
                    'id_uploader_firmado' => $idUploaderFirmado,
                ]);
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

                // --- Lógica condicional para asignar la evidencia correcta ---
                if ($gastoData['es_declaracion_jurada'] && $djConsolidada) {
                    $idDjParaGasto = $djConsolidada->id_dj_consolidada;
                    // La evidencia de un gasto de DJ es la propia DJ firmada, que ya está en la tabla consolidada.
                    // No se guarda una ruta de evidencia individual para este gasto.
                } elseif ($request->hasFile("gastos.{$index}.evidencia")) {
                    $pathEvidenciaIndividual = $request->file("gastos.{$index}.evidencia")->store('evidencias_gastos', 'public');
                } else {
                    // Si no es DJ y no tiene evidencia, es un error.
                    throw ValidationException::withMessages(["gastos.{$index}.evidencia" => 'Se requiere un archivo de evidencia para este gasto.']);
                }

                // --- Lógica de estado y aprobación inicial basada en roles ---
                $estadoInicial = 'Pendiente de Aprobación';
                $idJefeAprobador = null;
                $idValidadorAdm = null;
                $descontarSaldo = false;
                $fechaRendicion = null;
                $fechaLimiteRendicion = null;

                // REGLA 1: GASTOS DE PROYECTO
                // Si el fondo está asociado a un proyecto, la aprobación SIEMPRE va al responsable del proyecto.
                if ($fondo->id_proyecto && $fondo->solicitudApertura) {
                    // El aprobador es el solicitante original del fondo de apertura del proyecto.
                    $idJefeAprobador = $fondo->solicitudApertura->id_solicitante;
                    $estadoInicial = 'Pendiente de Aprobación';

                    // Caso especial: Si el mismo Jefe de Proyecto está registrando el gasto.
                    // Se auto-aprueba en primera instancia y pasa a validación contable.
                    if ($user->id === $idJefeAprobador) {
                        $estadoInicial = $idDjParaGasto ? 'Pendiente de Validación DJ' : 'Pendiente de Validación Contable';
                    }
                } else {
                    // REGLA 2: GASTOS DE ÁREA (FLUJO NORMAL)
                    // Si no es de proyecto, se aplican las reglas jerárquicas del área.
                    if ($user->hasAnyRole(['jefe_area', 'gerente_general', 'jefe_administracion'])) {
                        // Un jefe registrando en su propio fondo de área se auto-aprueba el primer nivel.
                        $idJefeAprobador = $user->id;
                        $estadoInicial = $idDjParaGasto ? 'Pendiente de Validación DJ' : 'Pendiente de Validación Contable';
                    } else {
                        // Un colaborador necesita la aprobación de su jefe de área directo.
                        $idJefeAprobador = $user->jefe_area_id;
                        $estadoInicial = 'Pendiente de Aprobación';
                    }
                }
                // REGLA 3: SUPER ADMIN (CASO ESPECIAL)
                // El Super Admin puede contabilizar directamente para agilizar procesos (siempre que no sea de proyecto).
                if ($user->hasRole('super_admin') && !$fondo->id_proyecto) {
                    $estadoInicial = $idDjParaGasto ? 'Pendiente de Validación DJ' : 'Contabilizado';
                    $idJefeAprobador = $user->id;
                    $idValidadorAdm = $user->id;
                }

                // --- Obtención de datos relacionados ---
                $gastoProyectadoDelCatalogo = $catalogoGastos->get($gastoData['id_gasto_proyectado']);
                $idCuentaContable = $gastoProyectadoDelCatalogo->id_cuenta_contable;
                $montoOriginal = $montosProyectadosOriginales->has($gastoData['id_gasto_proyectado'])
                    ? $montosProyectadosOriginales->get($gastoData['id_gasto_proyectado'])->pivot->monto_estimado
                    : 0;

                $calculosSaldo = Gasto::calculateExceededAmountAndAvailableBalance(
                    $gastoData['monto_total'],
                    $gastoData['id_gasto_proyectado'],
                    $fondo->id_fondo,
                    $montoOriginal,
                    $gastoData['fecha_documento']
                );
                $montoExcedido = $calculosSaldo['monto_excedido'];
                $saldoDisponibleAlRegistrar = $calculosSaldo['saldo_disponible'];
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
                    'monto_excedido_al_registrar' => $montoExcedido,
                    'saldo_disponible_al_registrar' => $saldoDisponibleAlRegistrar,
                    'fecha_rendicion' => $fechaRendicion,
                    'fecha_limite_rendicion' => $fechaLimiteRendicion,
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
    }

    /**
     * Paso 2: Aprueba un gasto. (Acción del Jefe de Área)
     */
    public function approve(Request $request, Gasto $gasto)
    {
        $user = Auth::user();

        if ($gasto->id_jefe_aprobador !== $user->id) {
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
            $gasto->fondoEfectivo()->decrement('monto_disponible', $gasto->monto_total);
            // 1. Se establece la fecha de rendición con la fecha y hora actual.
            $gasto->fecha_rendicion = now();

            // 2. Se calcula y guarda cuál era la fecha límite para este gasto (fin del mes de la fecha del documento).
            $gasto->fecha_limite_rendicion = Carbon::parse($gasto->fecha_documento)->endOfMonth();
            $estadoAnterior = $gasto->estado;
            $gasto->estado = 'Contabilizado';
            $gasto->id_validador_adm = $user->id;
            $gasto->save();

            $this->registrarHistorial($gasto, $estadoAnterior, 'Contabilizado', $user->id, $request->input('comentario', 'Gasto validado y contabilizado por administración.'));

            return response()->json(['message' => 'Gasto validado y contabilizado exitosamente.', 'gasto' => $gasto->fresh()]);
        });
    }

    /**
     * Paso 3: Observa un gasto. (Acción de Administración)
     */
    public function observe(Request $request, Gasto $gasto)
    {
        $user = Auth::user();
        $user->load('role');

        // 1. --- Validación de entrada ---
        $request->validate(['comentario' => 'required|string|max:2000']);

        // 2. --- Lógica de Autorización y Permisos por Rol ---
        $isJefeAreaAutorizado = ($user->hasRole('jefe_area') && $user->area_id === $gasto->registrador->area_id);
        $isAdministrador = $user->hasAnyRole(['jefe_administracion', 'super_admin']);


        if (!$isJefeAreaAutorizado && !$isAdministrador) {
            return response()->json(['message' => 'No tienes permiso para observar este gasto.'], 403);
        }

        // 3. --- Validación de Estado según el Rol ---
        $estadosValidosParaJefe = ['Pendiente de Aprobación'];
        $estadosValidosParaAdmin = ['Pendiente de Validación Contable', 'Pendiente de Validación DJ'];

        if ($isJefeAreaAutorizado && !in_array($gasto->estado, $estadosValidosParaJefe)) {
            return response()->json(['message' => 'Como Jefe de Área, solo puedes observar gastos pendientes de tu aprobación.'], 409);
        }
        if ($isAdministrador && !in_array($gasto->estado, $estadosValidosParaAdmin)) {
            return response()->json(['message' => 'Como Administrador, este gasto no se encuentra en un estado válido para ser observado.'], 409);
        }

        // 4. --- Ejecución de la Lógica de Negocio ---
        return DB::transaction(function () use ($gasto, $user, $request, $isJefeAreaAutorizado) {
            $estadoAnteriorGastoObservado = $gasto->estado; // Estado del gasto que se observó directamente
            $comentarioObservacion = $request->comentario;
            $djId = $gasto->id_dj_consolidada;

            // --- LÓGICA DE INVALIDACIÓN DE GRUPO DE DJ ---
            if ($djId) {
                // Obtener TODOS los gastos del grupo, incluido el que se está observando.
                // Usamos where('id_dj_consolidada', $djId) para obtener todo el paquete.
                $gastosDelGrupo = Gasto::where('id_dj_consolidada', $djId)->get();

                foreach ($gastosDelGrupo as $gastoMiembro) {
                    $estadoPrevioMiembro = $gastoMiembro->estado; // Estado individual antes de la observación
                    $gastoMiembro->id_dj_consolidada = null; // Romper el vínculo de la DJ para TODOS
                    $gastoMiembro->estado = 'Observado'; // CAMBIO CLAVE: TODOS los miembros pasan a OBSERVADO
                    $gastoMiembro->motivo_observacion_adm = $comentarioObservacion; // Añadir motivo de observación
                    $gastoMiembro->id_observador_adm = $user->id; // Añadir observador
                    $gastoMiembro->save();

                    // Registrar historial para cada gasto del grupo
                    $this->registrarHistorial(
                        $gastoMiembro,
                        $estadoPrevioMiembro,
                        'Observado',
                        $user->id,
                        "DJ consolidada invalidada debido a observación en Gasto {$gasto->codigo_gasto}. Este gasto ahora requiere corrección."
                    );
                }
            } else {
                // --- Lógica de Observación Individual (solo si NO pertenecía a un grupo DJ) ---
                // Si el gasto no era parte de un grupo DJ, solo se observa a sí mismo.
                $gasto->estado = 'Observado';
                $gasto->motivo_observacion_adm = $comentarioObservacion;
                $gasto->id_observador_adm = $user->id;
                $gasto->save();

                $rolObservador = $isJefeAreaAutorizado ? 'Jefe de Área' : 'Administración';
                $this->registrarHistorial(
                    $gasto,
                    $estadoAnteriorGastoObservado,
                    'Observado',
                    $user->id,
                    "Observado por {$rolObservador}: " . $comentarioObservacion
                );
            }

            // La respuesta final es sobre el gasto que fue originalmente el objetivo del PUT.
            // Es buena práctica devolver el estado actualizado de este gasto.
            return response()->json(['message' => 'Gasto(s) observado(s). El registrador será notificado para su corrección.', 'gasto' => $gasto->fresh()]);
        });
    }
    //Actualizar gasto observado.
    public function actualizarGastoObservado(Request $request, Gasto $gasto)
    {
        $user = Auth::user();

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
            'id_tipo_documento_comprobante' => 'required_if:es_declaracion_jurada,false|exists:tipos_documento_comprobante,id',
            'serie_documento' => 'nullable|required_if:es_declaracion_jurada,false|string|max:20',
            'correlativo_documento' => 'nullable|required_if:es_declaracion_jurada,false|string|max:50',
            'ruc_proveedor' => 'nullable|string|max:11|regex:/^[0-9]*$/',
            'monto_total' => 'required|numeric|min:0.01',
            'glosa' => 'required|string|max:1000',
            'comentario_subsanacion' => 'nullable|string|max:2000',
            'evidencia' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:10240',
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
            $datosOriginales = $gasto->only(['monto_total', 'glosa', 'fecha_documento', 'id_tipo_documento_comprobante', 'serie_documento', 'correlativo_documento']);
            // Almacenamos los cambios para el historial
            $cambiosParaHistorial = [];
            // Recalcular el monto excedido para el gasto corregido.
            $calculosSaldo = Gasto::calculateExceededAmountAndAvailableBalance(
                $validatedData['monto_total'],
                $gasto->id_gasto_proyectado,
                $gasto->id_fondo_efectivo,
                $gasto->monto_proyectado_original, // Usar el monto proyectado original del gasto
                $validatedData['fecha_documento'],
                $gasto->id // Excluir el gasto actual de la suma de gastos existentes
            );
            $validatedData['monto_excedido_al_registrar'] = $calculosSaldo['monto_excedido'];
            $validatedData['saldo_disponible_al_registrar'] = $calculosSaldo['saldo_disponible'];

            // 3.2. Actualizar el gasto con los datos validados.
            $gasto->update($validatedData);
            // Comparamos cada campo para registrar los cambios
            foreach ($datosOriginales as $campo => $valorOriginal) {
                if ($gasto->{$campo} != $valorOriginal) {
                    $valorNuevo = $gasto->{$campo};

                    // Si el campo es la clave foránea, buscamos los nombres.
                    if ($campo === 'id_tipo_documento_comprobante') {
                        $nombreAnterior = TipoDocumentoComprobante::find($valorOriginal)->nombre ?? $valorOriginal;
                        $nombreNuevo = TipoDocumentoComprobante::find($valorNuevo)->nombre ?? $valorNuevo;

                        $cambiosParaHistorial['Tipo de Documento'] = [
                            'anterior' => $nombreAnterior,
                            'nuevo' => $nombreNuevo,
                        ];
                    } elseif ($campo === 'fecha_documento') {
                        // El modelo castea este campo a un objeto Carbon, por lo que podemos usar format()
                        $fechaAnterior = $valorOriginal ? Carbon::parse($valorOriginal)->format('Y-m-d') : 'N/A';
                        $fechaNueva = $valorNuevo ? Carbon::parse($valorNuevo)->format('Y-m-d') : 'N/A';

                        $cambiosParaHistorial['Fecha de Documento'] = [
                            'anterior' => $fechaAnterior,
                            'nuevo' => $fechaNueva,
                        ];
                    } else {
                        // Para todos los demás campos, la lógica se mantiene.
                        $cambiosParaHistorial[$campo] = [
                            'anterior' => $valorOriginal,
                            'nuevo' => $valorNuevo,
                        ];
                    }
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
                'gasto' => $gasto->fresh()->load(['registrador.role', 'jefeAprobador'])
            ]);
        });
    }

    // MÉTODOS DE GESTIÓN DE DJ CONSOLIDADA
    public function consolidateDj(Request $request)
    {
        $validated = $request->validate([
            'gastos_ids' => 'required|array|min:1',
            'gastos_ids.*' => 'required|integer|exists:gastos,id',
            'dj_consolidada_file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $user = Auth::user();
        $gastosIds = $validated['gastos_ids'];
        $gastosAConsolidar = Gasto::whereIn('id', $gastosIds)->get();
        // --- Validaciones de Seguridad y Lógica de Negocio ---
        if ($gastosAConsolidar->isEmpty()) {
            return response()->json(['message' => 'No se encontraron los gastos especificados.'], 404);
        }
        $primerGasto = $gastosAConsolidar->first();
        $primerEstado = $primerGasto->estado;
        $primerFondoId = $primerGasto->id_fondo_efectivo;
        $estadosValidos = ['Pendiente de Aprobación', 'Pendiente de Validación Contable'];

        foreach ($gastosAConsolidar as $gasto) {
            // 1. Verificar permisos (que el gasto pertenezca al usuario)
            if ($gasto->id_registrador !== $user->id) {
                return response()->json(['message' => 'No tienes permiso para consolidar uno de los gastos seleccionados.'], 403);
            }
            // 2. Verificar consistencia de estado
            if ($gasto->estado !== $primerEstado) {
                return response()->json(['message' => 'Error: No se pueden consolidar gastos con estados diferentes.'], 409);
            }
            if ($gasto->id_fondo_efectivo !== $primerFondoId) {
                return response()->json(['message' => 'Error: No se pueden consolidar gastos que pertenecen a diferentes fondos de caja chica.'], 409);
            }
        }
        // Verificar que el estado del grupo sea válido
        if (!in_array($primerEstado, $estadosValidos)) {
            return response()->json(['message' => 'Los gastos no se encuentran en un estado válido para ser consolidados.'], 409);
        }
        return DB::transaction(function () use ($gastosAConsolidar, $user, $request, $primerFondoId) {
            $pathDj = $request->file('dj_consolidada_file')->store('djs_consolidadas', 'public');
            $djConsolidada = DjConsolidada::create([
                'fondo_efectivo_id' => $primerFondoId,
                'fecha_declaracion' => now(),
                'monto_total_declarado' => $gastosAConsolidar->sum('monto_total'),
                'estado' => 'Declarado',
                'creado_por' => $user->id,
                'ruta_documento_firmado' => $pathDj,
                'id_uploader_firmado' => $user->id,
            ]);

            $nuevoEstado = $user->hasAnyRole(['jefe_area', 'gerente_general', 'jefe_administracion', 'super_admin'])
                ? 'Pendiente de Validación DJ'
                : 'Pendiente de Aprobación';

            foreach ($gastosAConsolidar as $gasto) {
                $estadoAnterior = $gasto->estado;
                $gasto->update([
                    'id_dj_consolidada' => $djConsolidada->id_dj_consolidada,
                    'estado' => $nuevoEstado
                ]);

                $this->registrarHistorial(
                    $gasto,
                    $estadoAnterior,
                    $nuevoEstado,
                    $user->id,
                    "Gasto agrupado en la DJ Consolidada #{$djConsolidada->id_dj_consolidada}."
                );
            }

            return response()->json([
                'message' => 'Gastos consolidados exitosamente en una nueva Declaración Jurada.',
                'dj_consolidada' => $djConsolidada->load('gastos')
            ], 201);
        });
    }

    // MÉTODOS ADICIONALES PARA COMPLETAR EL FLUJO

    //Rechaza un gasto de forma definitiva. (Acción de Administración)
    public function reject(Request $request, Gasto $gasto)
    {
        $validated = $request->validate(['comentario' => 'required|string|max:2000']);
        $user = Auth::user();
        $isJefeArea = $user->hasRole('jefe_area') && $user->area_id === $gasto->registrador->area_id;
        $isAdministrador = $user->hasAnyRole(['jefe_administracion', 'super_admin']);

        // 1. Autorización y validación de estado en un solo bloque.
        if ($isJefeArea && $gasto->estado === 'Pendiente de Aprobación') {
            // El Jefe de Área puede rechazar.
        } elseif ($isAdministrador && $gasto->estado === 'Pendiente de Validación Contable') {
            // El Administrador puede rechazar.
        } else {
            return response()->json(['message' => 'No tienes permiso para rechazar este gasto o no está en un estado válido para ser rechazado.'], 403);
        }

        // 2. Ejecución de la lógica.
        return DB::transaction(function () use ($gasto, $user, $validated) {
            $estadoAnterior = $gasto->estado;
            $gasto->estado = 'Rechazado';
            $gasto->motivo_rechazo = $validated['comentario'];
            $gasto->id_jefe_aprobador = $gasto->id_jefe_aprobador ?? ($isJefeArea ? $user->id : null);
            $gasto->save();

            $rolRechazador = $isJefeArea ? 'Jefe de Área' : 'Administración';
            $this->registrarHistorial($gasto, $estadoAnterior, 'Rechazado', $user->id, "Rechazado por {$rolRechazador}: " . $validated['comentario']);

            return response()->json(['message' => 'Gasto rechazado exitosamente.', 'gasto' => $gasto->fresh()]);
        });
    }

    public function rejectDjGroup(Request $request, DjConsolidada $djConsolidada)
    {
        $validated = $request->validate(['comentario' => 'required|string|max:2000']);
        $user = Auth::user();
        $djConsolidada->load('gastos.registrador.area'); // Cargar relaciones necesarias para validaciones

        // 1. Autorización: Solo un jefe de área o administrador puede rechazar un grupo.
        $primerGasto = $djConsolidada->gastos->first();
        if (!$primerGasto) {
            return response()->json(['message' => 'Este grupo de DJ no contiene gastos válidos para ser rechazado.'], 404);
        }

        $isJefeAreaAutorizado = ($user->hasRole('jefe_area') && $user->area_id === $primerGasto->registrador->area_id);
        $isAdministrador = $user->hasAnyRole(['jefe_administracion', 'super_admin']);

        if (!$isJefeAreaAutorizado && !$isAdministrador) {
            return response()->json(['message' => 'No tienes permiso para rechazar este grupo de gastos.'], 403);
        }

        // 2. Validación de Estado: Asegurar que todos los gastos del grupo estén en un estado rechazable.
        // Para Jefes de Área: Solo pueden rechazar si está 'Pendiente de Aprobación'.
        // Para Administradores: Pueden rechazar si está 'Pendiente de Validación DJ' o 'Pendiente de Validación Contable'.
        foreach ($djConsolidada->gastos as $gasto) {
            if ($isJefeAreaAutorizado && $gasto->estado !== 'Pendiente de Aprobación') {
                return response()->json(['message' => 'Como Jefe de Área, solo puedes rechazar grupos que estén pendientes de tu aprobación.'], 409);
            }
            if ($isAdministrador && !in_array($gasto->estado, ['Pendiente de Validación DJ', 'Pendiente de Validación Contable'])) {
                return response()->json(['message' => 'Como Administrador, este grupo no se encuentra en un estado válido para ser rechazado.'], 409);
            }
        }

        // 3. Ejecución de la Lógica: Rechazar todos los gastos del grupo.
        return DB::transaction(function () use ($djConsolidada, $user, $validated, $isJefeAreaAutorizado) {
            foreach ($djConsolidada->gastos as $gasto) {
                $estadoAnterior = $gasto->estado;
                $gasto->estado = 'Rechazado';
                $gasto->motivo_rechazo = $validated['comentario']; // El comentario de rechazo aplica a todo el grupo

                // IMPORTANTE: NO se rompe el vínculo id_dj_consolidada aquí.
                // Los gastos rechazados mantienen su referencia a la DJ original para trazabilidad.
                // Si se necesita una nueva DJ, el colaborador deberá crearla desde 0.

                $gasto->save();

                $rolRechazador = $isJefeAreaAutorizado ? 'Jefe de Área' : 'Administración';
                $this->registrarHistorial($gasto, $estadoAnterior, 'Rechazado', $user->id, "Grupo de DJ rechazado por {$rolRechazador}: " . $validated['comentario']);
            }

            return response()->json(['message' => 'Grupo de DJ rechazado exitosamente.', 'dj_consolidada' => $djConsolidada->load('gastos')]);
        });
    }
    public function misGastos()
    {
        $user = Auth::user();

        $gastos = Gasto::with([
            'registrador.role',
            'registrador.area:id,name',
            'jefeAprobador:id,name,last_name',
            'validadorAdm:id,name,last_name',
            'cuentaContable:id,codigo_cuenta,descripcion',
            'fondoEfectivo:id_fondo,codigo_fondo,monto_aprobado',
            'gastoProyectado:id_gasto_proyectado,descripcion',
            'djConsolidada',
            'historialAprobaciones.usuarioAccion',
            'tipoDocumento'
        ])
            ->where('id_registrador', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($gastos);
    }

    /**
     * Muestra un gasto específico.
     */
    public function show(Gasto $gasto)
    {
        // La política de autorización se ha reescrito para ser más explícita y
        // seguir exactamente las reglas de negocio definidas.
        $user = Auth::user();

        // Regla 1: Super Admin y Jefe de Administración pueden ver todo.
        if ($user->hasAnyRole(['super_admin', 'jefe_administracion'])) {
            // Acceso concedido
        }
        // Regla 2: Jefe de Área puede ver los gastos de su propia área (incluidos los suyos).
        elseif ($user->hasRole('jefe_area') && $user->area_id === $gasto->registrador->area_id) {
            // Acceso concedido
        }
        // Regla 3: Cualquier usuario (Colaborador, Gerente General, etc.) puede ver sus propios gastos.
        elseif ($user->id === $gasto->id_registrador) {
            // Acceso concedido
        }
        // Si ninguna regla se cumple, se deniega el acceso.
        else {
            return response()->json(['message' => 'No tienes permiso para ver este gasto.'], 403);
        }

        // Si el acceso fue concedido, cargar y devolver el gasto.
        return response()->json($gasto->load([
            'registrador.role',
            'registrador.area',
            'jefeAprobador',
            'validadorAdm',
            'cuentaContable',
            'gastoProyectado',
            'historialAprobaciones.usuarioAccion',
            'tipoDocumento'
        ]));
    }
    public function getGastosParaAprobacion(Request $request)
    {
        $user = Auth::user();
        $scope = $request->input('scope', 'aprobacion_jefe');

        $query = Gasto::with([
            'registrador.role',
            'registrador.area:id,name',
            'jefeAprobador:id,name,last_name',
            'fondoEfectivo.proyecto', 
            'djConsolidada',
            'cuentaContable',
            'gastoProyectado',
            'tipoDocumento'
        ]);

        // --- LÓGICA DE AUTORIZACIÓN Y FILTRADO POR BANDEJA (SCOPE) ---
        if ($scope === 'aprobacion_jefe') {
            // Para la Bandeja de Aprobación de cualquier Jefe (Área, Proyecto, Admin, Gerente).
            // Muestra los gastos donde el usuario actual ha sido asignado como el aprobador de primer nivel.
            if ($user->hasAnyRole(['jefe_area', 'gerente_general', 'jefe_administracion', 'super_admin'])) {
                $query->where('estado', 'Pendiente de Aprobación')
                    ->where('id_jefe_aprobador', $user->id);
            } else {
                // Si no es un jefe, no tiene acceso a esta bandeja.
                return response()->json(['message' => 'No tienes permiso para acceder a esta bandeja.'], 403);
            }
        } elseif ($scope === 'validacion_contable') {
            // Para la Bandeja de Validación de Administración.
            if ($user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
                $query->whereIn('estado', ['Pendiente de Validación DJ', 'Pendiente de Validación Contable']);
            } else {
                return response()->json(['message' => 'No tienes permiso para acceder a la bandeja de validación contable.'], 403);
            }
        } else {
            return response()->json(['message' => 'Bandeja no reconocida.'], 400);
        }

        // --- FILTROS ADICIONALES ---
        if ($request->filled('codigo_gasto')) {
            $codigos = explode(',', $request->codigo_gasto);
            $query->whereIn('codigo_gasto', array_map('trim', $codigos));
        }

        $gastos = $query->orderBy('created_at', 'desc')->get();

        // --- AGRUPACIÓN Y FORMATO DE RESPUESTA ---
        $gastosAgrupados = $gastos->groupBy('id_dj_consolidada');
        $resultado = collect();

        foreach ($gastosAgrupados as $djId => $grupo) {
            if ($djId) { // Grupo de DJ
                $resultado->push([
                    'es_grupo' => true,
                    'id_dj_consolidada' => $djId,
                    'estado_grupo' => $grupo->first()->estado,
                    'monto_total_grupo' => $grupo->sum('monto_total'),
                    'registrador' => $grupo->first()->registrador,
                    'fecha_registro' => $grupo->first()->created_at,
                    'dj_consolidada' => $grupo->first()->djConsolidada,
                    'gastos' => $grupo->values(),
                ]);
            } else { // Gastos individuales
                foreach ($grupo as $gasto) {
                    $resultado->push([
                        'es_grupo' => false,
                        'gasto' => $gasto,
                    ]);
                }
            }
        }

        return response()->json($resultado->values());
    }

    /**
     * Aprueba un grupo completo de gastos asociados a una DJ Consolidada. (Acción de Jefe de Área)
     *
     * @param Request $request
     * @param DjConsolidada $djConsolidada
     * @return \Illuminate\Http\JsonResponse
     */
    public function approveDjGroup(Request $request, DjConsolidada $djConsolidada)
    {
        $user = Auth::user();
        // Cargar las relaciones necesarias para la validación.
        $djConsolidada->load('gastos.registrador');
        // --- 1. Autorización ---
        // Verificamos que el usuario sea un jefe de área y que el gasto pertenezca a un colaborador de su área.
        $primerGasto = $djConsolidada->gastos->first();
        if (!$primerGasto) {
            return response()->json(['message' => 'Este grupo de DJ no contiene gastos válidos.'], 404);
        }
        // Se verifica que el usuario sea el aprobador asignado en el PRIMER gasto del grupo.
        // Se asume que todos los gastos de una DJ tienen el mismo aprobador.
        if ($primerGasto->id_jefe_aprobador !== $user->id) {
            return response()->json(['message' => 'No tienes permiso para aprobar este grupo de gastos.'], 403);
        }
        // --- 2. Validación de Estado ---
        // Se comprueba que todos los gastos del grupo estén en el estado correcto antes de proceder.
        if ($djConsolidada->gastos->contains(fn($gasto) => $gasto->estado !== 'Pendiente de Aprobación')) {
            return response()->json(['message' => 'No se puede aprobar. Al menos un gasto no está en el estado correcto.'], 409);
        }
        // --- 3. Ejecución de la Lógica ---
        // La lógica de negocio dentro de la transacción es correcta y se mantiene.
        DB::transaction(function () use ($djConsolidada, $user, $request) {
            foreach ($djConsolidada->gastos as $gasto) {
                $estadoAnterior = $gasto->estado;
                $gasto->update([
                    'estado' => 'Pendiente de Validación DJ',
                    'id_jefe_aprobador' => $user->id,
                ]);
                // Se asume que $this->registrarHistorial existe en el controlador.
                $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Grupo de DJ aprobado por Jefe de Área.'));
            }
        });

        return response()->json(['message' => 'Grupo de DJ aprobado exitosamente. Pasa a validación de documento.']);
    }

    /**
     * Valida el documento de una DJ Consolidada. (Acción de Administración)
     * Esto mueve todos los gastos del grupo al siguiente estado para la validación contable.
     *
     * @param Request $request
     * @param DjConsolidada $djConsolidada
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateDjDocument(Request $request, DjConsolidada $djConsolidada)
    {
        $user = Auth::user();
        $djConsolidada->load('gastos');
        // --- 1. Autorización ---
        // Solo los administradores pueden validar el documento de la DJ.
        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'No tienes permiso para realizar esta acción.'], 403);
        }
        // --- 2. Validación de Estado ---
        if ($djConsolidada->gastos->contains(fn($gasto) => $gasto->estado !== 'Pendiente de Validación DJ')) {
            return response()->json(['message' => 'No se puede validar el documento. El grupo no está en el estado correcto.'], 409);
        }
        // --- 3. Ejecución de la Lógica ---
        // La lógica de negocio dentro de la transacción es correcta y se mantiene.
        DB::transaction(function () use ($djConsolidada, $user, $request) {
            foreach ($djConsolidada->gastos as $gasto) {
                $estadoAnterior = $gasto->estado;
                $gasto->update(['estado' => 'Pendiente de Validación Contable']);
                $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Documento de DJ validado por Administración.'));
            }
        });

        return response()->json(['message' => 'Documento de DJ validado. Los gastos están listos para la validación contable.']);
    }

    /**
     * Contabiliza un grupo completo de gastos de una DJ. (Acción de Administración)
     *
     * @param Request $request
     * @param DjConsolidada $djConsolidada
     * @return \Illuminate\Http\JsonResponse
     */
    public function finalizeDjGroupAsAccounted(Request $request, DjConsolidada $djConsolidada)
    {
        $user = Auth::user();
        $djConsolidada->load('gastos.fondoEfectivo');

        // 1. --- Autorización ---
        if (!$user->hasAnyRole(['jefe_administracion', 'super_admin'])) {
            return response()->json(['message' => 'No tienes permiso para esta acción.'], 403);
        }

        // 2. --- Validación de Estado ---
        foreach ($djConsolidada->gastos as $gasto) {
            if ($gasto->estado !== 'Pendiente de Validación Contable') {
                return response()->json(['message' => 'No se puede contabilizar el grupo. Al menos un gasto no está listo para ser contabilizado.'], 409);
            }
        }

        // 3. --- Ejecución de la Lógica ---
        return DB::transaction(function () use ($djConsolidada, $user, $request) {
            foreach ($djConsolidada->gastos as $gasto) {
                // Descontar el monto del fondo.
                $gasto->fondoEfectivo()->decrement('monto_disponible', $gasto->monto_total);

                $estadoAnterior = $gasto->estado;
                $updateData = [
                    'estado' => 'Contabilizado',
                    'id_validador_adm' => $user->id,
                    'fecha_rendicion' => now(),
                    'fecha_limite_rendicion' => Carbon::parse($gasto->fecha_documento)->endOfMonth(),
                ];
                $gasto->update($updateData);

                $this->registrarHistorial($gasto, $estadoAnterior, $gasto->estado, $user->id, $request->input('comentario', 'Gasto de grupo DJ validado y contabilizado.'));
            }

            return response()->json(['message' => 'Grupo de DJ contabilizado exitosamente.', 'dj_consolidada' => $djConsolidada->load('gastos')]);
        });
    }
    public function getReporteGastos(Request $request)
    {
        // Query base con todas las relaciones necesarias para el reporte.
        $query = Gasto::with([
            'registrador.role',
            'registrador.area:id,name',
            'djConsolidada',
            'cuentaContable',
            'fondoEfectivo',
            'gastoProyectado',
            'historialAprobaciones.usuarioAccion',
            'tipoDocumento'
        ]);

        // Aplicar filtros de búsqueda adicionales de la request.
        // El filtro 'texto' en el frontend busca en 'codigo_gasto' y 'glosa'.
        if ($request->filled('texto')) {
            $searchTerm = $request->texto;
            // Se comprueba si el string contiene comas.
            if (strpos($searchTerm, ',') !== false) {
                // Si contiene comas (viene de una alerta del dashboard), se convierte en un array y se usa whereIn.
                $codigos = explode(',', $searchTerm);
                $query->whereIn('codigo_gasto', $codigos);
            } else {
                // Si no contiene comas, es una búsqueda manual del usuario. Se busca en código y glosa.
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('codigo_gasto', 'like', '%' . $searchTerm . '%')
                        ->orWhere('glosa', 'like', '%' . $searchTerm . '%');
                });
            }
        }
        if ($request->filled('registrador_name')) {
            $searchTerm = strtolower($request->registrador_name);
            $query->whereHas('registrador', function ($q) use ($searchTerm) {
                $q->where(DB::raw("CONCAT(LOWER(name), ' ', LOWER(last_name))"), 'like', '%' . $searchTerm . '%');
            });
        }
        if ($request->filled('area_id')) {
            $query->whereHas('registrador', function ($q) use ($request) {
                $q->where('area_id', $request->area_id);
            });
        }
        // El filtro 'estado' ahora debe permitir 'Todos' y otros estados finales.
        if ($request->filled('estado') && $request->estado !== 'Todos') {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio); // Filtrar por fecha de registro
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->fecha_fin); // Filtrar por fecha de registro
        }
        if ($request->filled('id_gasto_proyectado')) {
            $query->where('id_gasto_proyectado', $request->id_gasto_proyectado);
        }

        $gastos = $query->orderBy('created_at', 'desc')->get();

        // Agrupar los gastos por DJ consolidada, similar a getGastosParaAprobacion,
        // para mantener la estructura esperada por el frontend.
        $gastosAgrupados = $gastos->groupBy('id_dj_consolidada');

        $resultado = collect();

        foreach ($gastosAgrupados as $djId => $grupo) {
            if ($djId) {
                $resultado->push([
                    'es_grupo' => true,
                    'id_dj_consolidada' => $djId,
                    'estado_grupo' => $grupo->first()->estado,
                    'monto_total_grupo' => $grupo->sum('monto_total'),
                    'registrador' => $grupo->first()->registrador,
                    'fecha_registro' => $grupo->first()->created_at,
                    'dj_consolidada' => $grupo->first()->djConsolidada,
                    'gastos' => $grupo->values(),
                ]);
            } else {
                foreach ($grupo as $gasto) {
                    $resultado->push([
                        'es_grupo' => false,
                        'gasto' => $gasto,
                    ]);
                }
            }
        }

        return response()->json($resultado->values());
    }

    /**
     * Exporta los gastos a un archivo Excel con dos hojas: Cabecera y Detalle.
     * Este método NO modifica el estado de los gastos.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function exportReport(Request $request)
    {
        // La lógica de filtrado será idéntica a getReporteGastos, pero sin la transformación de agrupamiento
        // ya que el exportador necesita los gastos individuales para las filas del Excel.
        $query = Gasto::with([
            'registrador.area',
            'cuentaContable',
            'fondoEfectivo.area',
            'fondoEfectivo.proyecto',
            'gastoProyectado.tipoImpuesto',
            'djConsolidada',
            'tipoDocumento'
        ]);

        // Aplicar filtros de la request (igual que en getReporteGastos)
        if ($request->filled('texto')) {
            $searchTerm = strtolower($request->texto);
            $query->where(function ($q) use ($searchTerm) {
                $q->where('codigo_gasto', 'like', '%' . $searchTerm . '%')
                    ->orWhere('glosa', 'like', '%' . $searchTerm . '%');
            });
        }
        if ($request->filled('registrador_name')) {
            $searchTerm = strtolower($request->registrador_name);
            $query->whereHas('registrador', function ($q) use ($searchTerm) {
                $q->where(DB::raw("CONCAT(LOWER(name), ' ', LOWER(last_name))"), 'like', '%' . $searchTerm . '%');
            });
        }
        if ($request->filled('area_id')) {
            $query->whereHas('registrador', function ($q) use ($request) {
                $q->where('area_id', $request->area_id);
            });
        }
        if ($request->filled('estado') && $request->estado !== 'Todos') {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }
        if ($request->filled('id_gasto_proyectado')) {
            $query->where('id_gasto_proyectado', $request->id_gasto_proyectado);
        }

        $gastosParaExportar = $query->orderBy('id', 'asc')->get();

        // --- OPTIMIZACIÓN N+1 ---
        // Obtenemos todas las fechas de contabilización en una sola consulta.
        $gastoIds = $gastosParaExportar->pluck('id');
        $fechasContabilizacion = HistorialAprobacionGasto::whereIn('id_gasto', $gastoIds)
            ->where('estado_nuevo', 'Contabilizado')
            ->pluck('created_at', 'id_gasto'); // Clave: id_gasto, Valor: created_at

        // --- DATOS PARA LA HOJA "DETALLE" ---
        $detalleHeadings = [
            'Número Correlativo',
            'Cuenta Contable',
            'Descripción del Servicio',
            'Base Imponible',
            'Tipo de Impuesto',
            'Centro de Costo',
            'Proyecto',
            'Kilowatts',
            'Retención',
        ];

        $detalleData = $gastosParaExportar->map(function ($gasto) {
            // --- LÓGICA DE BASE IMPONIBLE ---
            $baseImponible = 0;
            $tipoImpuesto = 'N/A';

            $gastoProyectado = $gasto->gastoProyectado;

            if ($gastoProyectado && $gastoProyectado->tipoImpuesto) {
                $tipoImpuesto = $gastoProyectado->tipoImpuesto->nombre;
                $factorCalculo = $gastoProyectado->tipoImpuesto->factor_calculo ?? 1;

                // Calcular base imponible de forma genérica
                if ($factorCalculo > 0) {
                    $baseImponible = $gasto->monto_total / $factorCalculo;
                } else {
                    // Si el factor no es válido (ej. 0), usar monto total
                    $baseImponible = $gasto->monto_total;
                }
            } else {
                // Si no hay tipo de impuesto, usar monto total
                $baseImponible = $gasto->monto_total;
            }


            // --- LÓGICA DE CENTRO DE COSTO ---
            $centroCosto = 'N/A';

            if ($gasto->fondoEfectivo && $gasto->fondoEfectivo->area && $gasto->fondoEfectivo->area->centroCosto) {
                $centroCosto = $gasto->fondoEfectivo->area->centroCosto->codigo;
            }


            // --- LÓGICA DE PROYECTO ---
            $proyecto = 'N/A';
            if ($gasto->fondoEfectivo && $gasto->fondoEfectivo->proyecto) {
                $proyecto = $gasto->fondoEfectivo->proyecto->nombre ?? 'N/A';
            }

            return [
                $gasto->id, // Número Correlativo
                $gasto->cuentaContable->codigo_cuenta ?? 'N/A', // Cuenta Contable
                $gasto->glosa, // Descripción del Servicio (Glosa)
                number_format($baseImponible, 2, '.', ''), // Base Imponible
                $tipoImpuesto, // Tipo de Impuesto
                $centroCosto, // Centro de Costo
                $proyecto, // Proyecto
                '', // Kilowatts (vacío)
                'N', // Retención (siempre N)
            ];
        })->toArray();

        // --- DATOS PARA LA HOJA "CABECERA" ---
        $cabeceraHeadings = [
            'Numero Correlativo',
            'Serie SAP',
            'Código Del SN',
            'Fecha de Contabilización',
            'Fecha del Documento',
            'Fecha de Vencimiento',
            'Moneda del Documento',
            'Tipo de Cambio',
            'Total del Documento',
            'Tipo de Documento',
            'Glosa para el Asiento',
            'Tipo de Documento',
            'Serie del Documento',
            'Correlativo del Documento',
            'Referencia de documento',
            'Clasificación de Bienes y Servicios',
            'Tipo de Rendición',
            'Serie de Rendición',
            'Correlativo Rendición',
            'Referencia de Rendición',
            'Comentarios',
        ];

        $cabeceraData = $gastosParaExportar->map(function ($gasto) {
            // --- LÓGICA DE FECHAS UNIFICADAS ---
            $fechaDocumento = $gasto->fecha_documento ? \Carbon\Carbon::parse($gasto->fecha_documento) : \Carbon\Carbon::parse($gasto->created_at);
            $fechaFormateada = $fechaDocumento->format('Ymd');
            $fechaFormateadaRendicion = $fechaDocumento->format('Ym'); // Formato AAAAMM para rendición
            
            
            // --- LÓGICA DE CÓDIGO DEL SN ---
            $codigoSN = 'P99999999999'; // Por defecto
            if (!empty($gasto->ruc_proveedor) && in_array($gasto->tipoDocumento->nombre ?? '', ['Factura', 'Boleta de Venta', 'Recibo por Honorarios'])) {
                $codigoSN = 'P' . $gasto->ruc_proveedor;
            }

            // --- LÓGICA DE SERIE Y CORRELATIVO DEL DOCUMENTO ---
            $tipoDocumento = $gasto->tipoDocumento->codigo_comprobante ?? 'N/A';
            
            // Para Declaraciones Juradas
            if ($gasto->es_declaracion_jurada) {
                $serie = 'DJ';
                $correlativo = $fechaFormateadaRendicion; // Fecha del documento
            } else {
                // Para otros documentos
            $serie = $gasto->serie_documento ?? 'N/A';
            $correlativo = $gasto->correlativo_documento ?? 'N/A';
            }
            
            $documentoCompleto = "{$tipoDocumento}-{$serie}-{$correlativo}";

            // --- LÓGICA DE RENDICIÓN ---
            // Tipo de Rendición: siempre ER (Entregas a Rendir)
            $tipoRendicion = 'ER';
            
            // Serie de Rendición: acrónimo del ejecutor (3 siglas: primer nombre + dos apellidos)
            $nombreCompleto = $gasto->registrador->name . ' ' . $gasto->registrador->last_name;
            $palabras = explode(' ', trim($nombreCompleto));
            $serieRendicion = '';
            if (count($palabras) >= 3) {
                $serieRendicion = strtoupper(substr($palabras[0], 0, 1) . substr($palabras[1], 0, 1) . substr($palabras[2], 0, 1));
            } elseif (count($palabras) == 2) {
                $serieRendicion = strtoupper(substr($palabras[0], 0, 1) . substr($palabras[1], 0, 2));
            } else {
                $serieRendicion = strtoupper(substr($palabras[0], 0, 3));
            }
            
            // Correlativo Rendición: fecha en formato AAAAMM
            $correlativoRendicion = $fechaFormateadaRendicion;
            
            // Referencia de Rendición: concatenado
            $referenciaRendicion = "{$tipoRendicion}-{$serieRendicion}-{$correlativoRendicion}";

            return [
                $gasto->id, // Numero Correlativo
                $gasto->created_at->format('Y'), // Serie SAP (según año)
                $codigoSN, // Código Del SN
                $fechaFormateada, // Fecha de Contabilización
                $fechaFormateada, // Fecha del Documento
                $fechaFormateada, // Fecha de Vencimiento
                'SOL', // Moneda del Documento
                '', // Tipo de Cambio (vacío)
                number_format($gasto->monto_total, 2, '.', ''), // Total del Documento
                'dDocument_Service', // Tipo de Documento (por defecto)
                $gasto->glosa, // Glosa para el Asiento
                $tipoDocumento, // Tipo de Documento (SUNAT)
                $serie, // Serie del Documento
                $correlativo, // Correlativo del Documento
                $documentoCompleto, // Referencia de documento
                $gasto->gastoProyectado->ClasificacionBienServicio->codigo ?? 'N/A', // Clasificación de Bienes y Servicios
                $tipoRendicion, // Tipo de Rendición (ER)
                $serieRendicion, // Serie de Rendición (acrónimo)
                $correlativoRendicion, // Correlativo Rendición (AAAAMM)
                $referenciaRendicion, // Referencia de Rendición (concatenado)
                $gasto->comentario ?? 'N/A', // Comentarios
            ];
        })->toArray();

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\GastosReportExport($detalleData, $detalleHeadings, $cabeceraData, $cabeceraHeadings), 
            'reporte_gastos_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    /**
     * Elimina un gasto.
     */
    public function destroy(Gasto $gasto)
    {
        $user = Auth::user();
        $canDelete = ($gasto->id_registrador === $user->id && in_array($gasto->estado, ['Pendiente de Aprobación', 'Observado']));

        if (!$canDelete && !$user->hasRole('super_admin')) {
            return response()->json(['message' => 'No tienes permiso para eliminar este gasto o ya no se puede eliminar.'], 403);
        }

        DB::transaction(function () use ($gasto) {
            if ($gasto->ruta_evidencia) {
                Storage::disk('public')->delete($gasto->ruta_evidencia);
            }
            $gasto->historialAprobaciones()->delete();
            $gasto->delete();
        });
        return response()->json(['message' => 'Gasto eliminado exitosamente.']);
    }
}
