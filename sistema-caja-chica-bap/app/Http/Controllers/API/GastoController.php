<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Gasto;
use App\Models\Evidencia;
use App\Models\DeclaracionJurada;
use App\Models\DetalleDeclaracionJurada;
use App\Models\FondoEfectivo; // Necesario para vincular gastos a un fondo
use App\Models\ClasificacionGasto; // Necesario para validación/desplegables
use App\Models\TipoComprobante; // Necesario para validación/desplegables
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage; // Para la carga de archivos
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB; // Para transacciones de base de datos

class GastoController extends Controller
{
    /**
     * Muestra una lista de todos los gastos.
     * La visibilidad de los gastos depende del rol del usuario autenticado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Cargar relaciones necesarias para la visualización detallada de los gastos
        $query = Gasto::with([
            'personalRegistra', // Usuario que registró el gasto
            'personalRealizaGasto', // Usuario que realizó el gasto
            'clasificacionGasto', // Clasificación contable del gasto
            'tipoComprobante', // Tipo de comprobante (Boleta, Factura, DJ)
            'evidencias', // Archivos adjuntos (imágenes, PDFs)
            'declaracionJurada', // Información de la Declaración Jurada si aplica
            'declaracionJurada.detalles', // Detalles de la Declaración Jurada
            'fondoEfectivo', // Fondo de efectivo al que pertenece el gasto
            'validadorJa', // Usuario Jefe de Área que validó
            'validadorAdm', // Usuario Jefe de Administración que validó/observó
            'observaciones' // Observaciones realizadas al gasto
        ]);

        // Lógica para filtrar los gastos según el rol del usuario
        if ($user->hasRole('super_admin') || $user->hasRole('gerente_general')) {
            // Super Admin y Gerente General pueden ver todos los gastos del sistema
            // No se añade ninguna condición de filtrado adicional
        } elseif ($user->hasRole('jefe_administracion')) {
            // El Jefe de Administración puede ver todos los gastos de todas las áreas
            // que están pendientes de su validación o ya han sido validados.
            // Podría añadirse un filtro por área si solo ve las de su responsabilidad.
        } elseif ($user->hasRole('jefe_area')) {
            // El Jefe de Área ve sus propios gastos y los gastos de los colaboradores de su área
            $query->where('id_personal_registra', $user->id) // Gastos que él mismo registró
                  ->orWhere('id_personal_realiza_gasto', $user->id) // Gastos que él mismo realizó
                  ->orWhereHas('personalRealizaGasto', function ($q) use ($user) {
                      // Gastos realizados por colaboradores que reportan a este Jefe de Área
                      $q->where('jefe_area_id', $user->id);
                  });
        } elseif ($user->hasRole('colaborador')) {
            // El Colaborador solo ve los gastos que él mismo realizó
            $query->where('id_personal_realiza_gasto', $user->id);
        } else {
            // Rol no reconocido o sin permisos para ver gastos
            return response()->json(['message' => 'Acceso denegado. No tienes permisos para ver gastos.'], 403);
        }

        // Ordenar los gastos por fecha de registro del sistema (más recientes primero)
        $gastos = $query->orderBy('fecha_registro_sistema', 'desc')->get();

        return response()->json([
            'message' => 'Gastos obtenidos exitosamente.',
            'gastos' => $gastos,
        ]);
    }

    /**
     * Almacena un nuevo gasto en la base de datos.
     * Incluye la lógica para manejar comprobantes, declaraciones juradas y archivos de evidencia.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        // Obtener el ID del tipo de comprobante 'Declaracion Jurada' para la validación condicional
        $tipoDJ = TipoComprobante::where('nombre', 'Declaracion Jurada')->first();
        $tipoDJId = $tipoDJ ? $tipoDJ->id : null; // Asegurarse de que exista

        // Validar los datos de entrada del gasto
        $request->validate([
            'fecha_documento' => 'required|date',
            'moneda' => 'required|string|max:10',
            'total_documento' => 'required|numeric|min:0.01',
            'glosa' => 'required|string|max:1000',
            'id_tipo_comprobante' => 'required|exists:tipo_comprobantes,id',
            'serie_documento' => 'nullable|string|max:50',
            'correlativo_documento' => 'nullable|string|max:50',
            'id_fondo' => 'required|exists:fondo_efectivo,id',
            'id_personal_realiza_gasto' => 'required|exists:users,id',
            'id_clasificacion' => 'required|exists:clasificacion_gastos,id',
            'evidencia_archivos.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120', // Máximo 5MB por archivo

            // Validación condicional para Declaración Jurada
            'declaracion_jurada' => 'nullable|array',
            'declaracion_jurada.nombre_declarante' => 'required_if:id_tipo_comprobante,'.$tipoDJId.'|string|max:255',
            'declaracion_jurada.dni_declarante' => 'required_if:id_tipo_comprobante,'.$tipoDJId.'|string|max:50',
            'declaracion_jurada.cargo_declarante' => 'required_if:id_tipo_comprobante,'.$tipoDJId.'|string|max:255',
            'declaracion_jurada.importe_total_declarado' => 'required_if:id_tipo_comprobante,'.$tipoDJId.'|numeric|min:0.01',
            'declaracion_jurada.actividad_proyecto' => 'nullable|string|max:255',
            'declaracion_jurada.detalles' => 'required_if:id_tipo_comprobante,'.$tipoDJId.'|array|min:1',
            'declaracion_jurada.detalles.*.fecha_detalle' => 'required|date',
            'declaracion_jurada.detalles.*.concepto_detalle' => 'required|string|max:255',
            'declaracion_jurada.detalles.*.monto_detalle' => 'required|numeric|min:0.01',
        ]);

        // Iniciar una transacción de base de datos para asegurar la atomicidad de la operación
        DB::beginTransaction();
        try {
            $user = Auth::user(); // Obtener el usuario autenticado (quien registra el gasto)

            // Crear el registro principal del Gasto
            $gasto = Gasto::create([
                'fecha_registro_sistema' => now(), // Fecha y hora de registro en el sistema
                'fecha_documento' => $request->fecha_documento,
                'moneda' => $request->moneda,
                'total_documento' => $request->total_documento,
                'glosa' => $request->glosa,
                'id_tipo_comprobante' => $request->id_tipo_comprobante,
                'serie_documento' => $request->serie_documento,
                'correlativo_documento' => $request->correlativo_documento,
                'estado_validacion_ja' => 'Pendiente', // Estado inicial de validación por Jefe de Área
                'estado_validacion_adm' => 'Pendiente', // Estado inicial de validación por Jefe de Administración
                'id_fondo' => $request->id_fondo,
                'id_personal_registra' => $user->id, // El ID del usuario que está logueado y registra
                'id_personal_realiza_gasto' => $request->id_personal_realiza_gasto, // El ID de la persona que realmente gastó
                'id_clasificacion' => $request->id_clasificacion,
            ]);

            // Si el tipo de comprobante es "Declaracion Jurada", crear los registros asociados
            if ($request->id_tipo_comprobante === $tipoDJId && $request->has('declaracion_jurada')) {
                $djData = $request->declaracion_jurada;
                $declaracionJurada = $gasto->declaracionJurada()->create([
                    'nombre_declarante' => $djData['nombre_declarante'],
                    'dni_declarante' => $djData['dni_declarante'],
                    'cargo_declarante' => $djData['cargo_declarante'],
                    'importe_total_declarado' => $djData['importe_total_declarado'],
                    'actividad_proyecto' => $djData['actividad_proyecto'] ?? null,
                ]);

                // Crear los detalles de la Declaración Jurada
                foreach ($djData['detalles'] as $detalle) {
                    $declaracionJurada->detalles()->create($detalle);
                }
            }

            // Manejar la carga de archivos de evidencia
            if ($request->hasFile('evidencia_archivos')) {
                foreach ($request->file('evidencia_archivos') as $file) {
                    // Guardar el archivo en el disco 'public' dentro de la carpeta 'evidencias'
                    $path = $file->store('evidencias', 'public');
                    // Crear un registro en la tabla 'evidencias'
                    $gasto->evidencias()->create([
                        'nombre_archivo' => $file->getClientOriginalName(),
                        'ruta_archivo' => $path,
                        'tipo_archivo' => $file->getClientMimeType(),
                        'fecha_carga' => now(),
                    ]);
                }
            }

            DB::commit(); // Confirmar la transacción si todo fue exitoso

            return response()->json([
                'message' => 'Gasto registrado exitosamente.',
                // Cargar relaciones para la respuesta, incluyendo los detalles de la DJ
                'gasto' => $gasto->load(['evidencias', 'declaracionJurada.detalles', 'clasificacionGasto', 'tipoComprobante']),
            ], 201); // Código de estado 201 para "Created"

        } catch (ValidationException $e) {
            DB::rollBack(); // Revertir la transacción en caso de error de validación
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422); // Código de estado 422 para errores de validación
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir la transacción en caso de cualquier otro error
            return response()->json([
                'message' => 'Ocurrió un error al registrar el gasto.',
                'error' => $e->getMessage(), // Mensaje de error para depuración (no en producción)
            ], 500); // Código de estado 500 para errores internos del servidor
        }
    }

    /**
     * Muestra un gasto específico por su ID.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            // Cargar todas las relaciones necesarias para la vista detallada de un gasto
            $gasto = Gasto::with([
                'personalRegistra',
                'personalRealizaGasto',
                'clasificacionGasto',
                'tipoComprobante',
                'evidencias',
                'declaracionJurada',
                'declaracionJurada.detalles',
                'fondoEfectivo',
                'validadorJa',
                'validadorAdm',
                'observaciones'
            ])->findOrFail($id); // Buscar el gasto por ID o lanzar excepción 404

            // Lógica de autorización para ver el gasto
            $user = Auth::user();
            // Permitir ver el gasto si es super_admin, gerente_general, jefe_administracion,
            // o si es el usuario que lo registró, o el que lo realizó,
            // o si es el jefe de área del usuario que realizó el gasto.
            if (!($user->hasRole('super_admin') || $user->hasRole('gerente_general') || $user->hasRole('jefe_administracion')) &&
                $user->id !== $gasto->id_personal_registra &&
                $user->id !== $gasto->id_personal_realiza_gasto &&
                !($user->hasRole('jefe_area') && $gasto->personalRealizaGasto && $gasto->personalRealizaGasto->jefe_area_id === $user->id)) {
                return response()->json(['message' => 'Acceso denegado. No tienes permisos para ver este gasto.'], 403);
            }


            return response()->json([
                'message' => 'Gasto obtenido exitosamente.',
                'gasto' => $gasto,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Gasto no encontrado.'], 404);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ocurrió un error al obtener el gasto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el estado de validación de un gasto o permite que el Jefe de Área realice un descargo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function update(Request $request, $id)
    {
        try {
            $gasto = Gasto::findOrFail($id);
            $user = Auth::user();

            // Validar los campos de actualización de estado o descargo
            $request->validate([
                'estado_validacion_ja' => 'nullable|in:Validado,Rechazado',
                'motivo_rechazo_ja' => 'required_if:estado_validacion_ja,Rechazado|string|max:500',
                'estado_validacion_adm' => 'nullable|in:Revisado,Observado,Aprobado Final',
                'comentarios_adm' => 'required_if:estado_validacion_adm,Observado|string|max:1000',
                'descargo_jefe_area' => 'nullable|string|max:1000', // Campo para el descargo
            ]);

            DB::beginTransaction(); // Iniciar transacción

            // Lógica para la validación por el Jefe de Área
            if ($request->has('estado_validacion_ja')) {
                // Solo el Jefe de Área del usuario que realizó el gasto o el que lo registró puede validar
                if (!($user->hasRole('jefe_area') && ($user->id === $gasto->personalRealizaGasto->jefe_area_id || $user->id === $gasto->id_personal_registra))) {
                    return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Área puede validar este gasto.'], 403);
                }
                // Asegurarse de que el gasto esté pendiente de validación por JA
                if ($gasto->estado_validacion_ja !== 'Pendiente') {
                    return response()->json(['message' => 'Este gasto ya fue validado por el Jefe de Área.'], 400);
                }

                $gasto->estado_validacion_ja = $request->estado_validacion_ja;
                $gasto->fecha_validacion_ja = now();
                $gasto->id_validador_ja = $user->id;
                $gasto->motivo_rechazo_ja = $request->motivo_rechazo_ja;

                // Si el gasto es rechazado por el Jefe de Área, su estado final para ADM también cambia
                if ($request->estado_validacion_ja === 'Rechazado') {
                    $gasto->estado_validacion_adm = 'Rechazado por JA'; // Estado final para el flujo de ADM
                }

            }
            // Lógica para la validación por el Jefe de Administración
            elseif ($request->has('estado_validacion_adm')) {
                if (!($user->hasRole('jefe_administracion') || $user->hasRole('super_admin'))) {
                    return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Administración puede validar/observar este gasto.'], 403);
                }

                // El Jefe de Administración solo puede validar si el gasto está 'Pendiente' (después de JA) o 'Observado' por él mismo
                if (!in_array($gasto->estado_validacion_adm, ['Pendiente', 'Observado'])) {
                     return response()->json(['message' => 'El estado actual del gasto no permite esta acción por parte de Administración.'], 400);
                }

                $gasto->estado_validacion_adm = $request->estado_validacion_adm;
                $gasto->fecha_revision_adm = now();
                $gasto->id_validador_adm = $user->id;
                $gasto->comentarios_adm = $request->comentarios_adm;

                // Si el estado es 'Observado', se crea una nueva observación
                if ($request->estado_validacion_adm === 'Observado') {
                    $gasto->observaciones()->create([
                        'descripcion_observacion' => $request->comentarios_adm,
                        'id_revisor_adm' => $user->id,
                        'fecha_observacion' => now(),
                        'estado' => 'Pendiente', // Estado inicial de la observación
                    ]);
                }
            }
            // Lógica para que el Jefe de Área realice un descargo a una observación
            elseif ($request->has('descargo_jefe_area')) {
                // Solo el Jefe de Área del gasto o el que lo registró puede hacer un descargo
                if (!($user->hasRole('jefe_area') && ($user->id === $gasto->id_personal_realiza_gasto || $user->id === $gasto->id_personal_registra))) {
                    return response()->json(['message' => 'Acceso denegado. Solo el Jefe de Área puede enviar un descargo para este gasto.'], 403);
                }
                // Asegurarse de que el gasto esté en estado "Observado" por ADM
                if ($gasto->estado_validacion_adm !== 'Observado') {
                    return response()->json(['message' => 'Este gasto no está en estado "Observado" para realizar un descargo.'], 400);
                }

                // Encontrar la última observación pendiente y actualizarla con el descargo
                $latestObservation = $gasto->observaciones()->where('estado', 'Pendiente')->latest()->first();
                if ($latestObservation) {
                    $latestObservation->update([
                        'descargo_jefe_area' => $request->descargo_jefe_area,
                        'fecha_descargo' => now(),
                        'estado' => 'Respondida', // La observación ha sido respondida
                    ]);
                    // Opcional: Cambiar el estado del gasto a "Descargo Enviado" para que el Jefe ADM lo revise de nuevo
                    // $gasto->estado_validacion_adm = 'Descargo Enviado ADM';
                } else {
                    return response()->json(['message' => 'No hay observaciones pendientes para este gasto.'], 400);
                }
            } else {
                // Si no se proporcionaron campos de estado o descargo, devolver un error
                return response()->json(['message' => 'No se proporcionaron campos válidos para actualizar el gasto.'], 400);
            }

            $gasto->save(); // Guardar los cambios en el gasto
            DB::commit(); // Confirmar la transacción

            return response()->json([
                'message' => 'Gasto actualizado exitosamente.',
                // Cargar relaciones para la respuesta actualizada
                'gasto' => $gasto->load(['evidencias', 'declaracionJurada.detalles', 'clasificacionGasto', 'tipoComprobante', 'observaciones']),
            ]);
        } catch (ModelNotFoundException $e) {
            DB::rollBack(); // Revertir transacción
            return response()->json(['message' => 'Gasto no encontrado.'], 404);
        } catch (ValidationException $e) {
            DB::rollBack(); // Revertir transacción
            return response()->json([
                'message' => 'Error de validación.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir transacción
            return response()->json([
                'message' => 'Ocurrió un error al actualizar el gasto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Elimina un gasto de la base de datos.
     * Solo es posible si el gasto está en un estado inicial o si el usuario es un super_admin.
     * También elimina archivos de evidencia y registros relacionados (DJ, Observaciones).
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $gasto = Gasto::findOrFail($id);
            $user = Auth::user();

            // Lógica de autorización para eliminar el gasto
            // Solo el usuario que registró/realizó el gasto (si está en estado inicial 'Pendiente')
            // o un super_admin puede eliminarlo.
            if ((($user->id === $gasto->id_personal_registra || $user->id === $gasto->id_personal_realiza_gasto) &&
                ($gasto->estado_validacion_ja === 'Pendiente' && $gasto->estado_validacion_adm === 'Pendiente')) ||
                $user->hasRole('super_admin')) {

                DB::beginTransaction(); // Iniciar transacción

                // Eliminar archivos de evidencia del almacenamiento y sus registros de BD
                foreach ($gasto->evidencias as $evidencia) {
                    Storage::disk('public')->delete($evidencia->ruta_archivo); // Eliminar archivo físico
                    $evidencia->delete(); // Eliminar registro de la tabla 'evidencias'
                }

                // Eliminar la declaración jurada y sus detalles si existe
                if ($gasto->declaracionJurada) {
                    $gasto->declaracionJurada->detalles()->delete(); // Eliminar detalles primero
                    $gasto->declaracionJurada->delete(); // Luego eliminar la DJ
                }

                // Eliminar observaciones asociadas al gasto
                $gasto->observaciones()->delete();

                $gasto->delete(); // Finalmente, eliminar el registro del gasto
                DB::commit(); // Confirmar la transacción

                return response()->json(['message' => 'Gasto eliminado exitosamente.'], 200);
            } else {
                return response()->json(['message' => 'Acceso denegado. No tienes permisos para eliminar este gasto o su estado no lo permite.'], 403);
            }
        } catch (ModelNotFoundException $e) {
            DB::rollBack(); // Revertir transacción
            return response()->json(['message' => 'Gasto no encontrado.'], 404);
        } catch (\Exception $e) {
            DB::rollBack(); // Revertir transacción
            return response()->json([
                'message' => 'Ocurrió un error al eliminar el gasto.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
