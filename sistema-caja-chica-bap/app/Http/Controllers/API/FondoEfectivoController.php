<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FondoEfectivo; // Importar el modelo FondoEfectivo
use App\Models\SolicitudFondo; // Para posibles relaciones o lógica de negocio
use App\Models\DetalleGastoProyectado; // Asegúrate de importar el modelo DetalleGastoProyectado
use Illuminate\Support\Facades\Auth; // Para obtener el usuario autenticado
use Illuminate\Database\Eloquent\ModelNotFoundException; // Para manejar si no se encuentra un recurso
use Illuminate\Support\Facades\DB; // Para operaciones de base de datos como DB::raw
use Illuminate\Validation\ValidationException; // Para manejar errores de validación
use Illuminate\Support\Facades\Log; // Para logging

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

        // Verificar si el usuario está autenticado
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // Cargar relaciones necesarias para la visualización detallada de los fondos
        $query = FondoEfectivo::query();

        $query->with([
            'responsable:id,name,last_name,email,cargo', // Usuario responsable del fondo
            'area:id,name', // Área a la que pertenece el fondo
            'solicitudApertura' => function ($q) { // Cargar la solicitud de apertura
                $q->select('id', 'codigo_solicitud', 'tipo_solicitud', 'monto_solicitado', 'estado', 'id_revisor_adm', 'id_aprobador_gerente'); // Incluir IDs de revisores/aprobadores

                // Cargar los detalles de gastos proyectados de la solicitud de apertura
                $q->with('detallesGastosProyectados:id,id_solicitud_fondo,descripcion_gasto,monto_estimado');

                // Cargar los datos del revisor de ADM de la solicitud de apertura
                $q->with('revisorAdm:id,name,last_name');

                // Cargar los datos del aprobador Gerente de la solicitud de apertura
                $q->with('aprobadorGerente:id,name,last_name');

                // Cargar el historial de estados de la solicitud de apertura
                $q->with(['historialEstados' => function ($qHist) {
                    $qHist->orderBy('created_at', 'asc')->with('usuarioAccion:id,name,last_name');
                }]);
            }
        ]);

        // Lógica para filtrar fondos según el rol del usuario
        if ($user->hasRole('super_admin') || $user->hasRole('gerente_general') || $user->hasRole('jefe_administracion')) {
            // Super Admin, Gerente General y Jefe de Administración pueden ver todos los fondos
            // Los filtros se aplicarán sobre el conjunto completo de fondos
        } elseif ($user->hasRole('jefe_area') || $user->hasRole('colaborador')) {
            // Jefe de Área y Colaborador solo ven los fondos de los que son responsables.
            $query->where('id_responsable', $user->id); // Solo los fondos donde el usuario es el responsable
        } else {
            // Rol no reconocido o sin permisos
            return response()->json(['message' => 'Acceso denegado. No tienes permisos para ver fondos de efectivo.'], 403);
        }

        // Aplicar filtros adicionales si vienen en la request
        if ($request->has('estado') && $request->estado !== 'Todos') {
            $query->where('estado', $request->estado);
        }
        if ($request->has('codigo_fondo')) {
            $query->where('codigo_fondo', 'like', '%' . $request->codigo_fondo . '%');
        }
        if ($request->has('fecha_apertura')) {
            // Asume que la fecha_apertura viene en formato 'YYYY-MM-DD'
            $query->whereDate('fecha_apertura', $request->fecha_apertura);
        }

        // Filtro por nombre de responsable (si se proporciona)
        if ($request->has('responsable_name')) {
            $searchTerm = strtolower($request->responsable_name);
            $query->whereHas('responsable', function ($q) use ($searchTerm) {
                $q->where(DB::raw('LOWER(name)'), 'like', '%' . $searchTerm . '%')
                    ->orWhere(DB::raw('LOWER(last_name)'), 'like', '%' . $searchTerm . '%');
            });
        }

        // El filtro por area_id solo se aplica si el usuario es JADM, GG o SA
        // y si el parámetro area_id está presente en la request.
        if (($user->hasRole('super_admin') || $user->hasRole('gerente_general') || $user->hasRole('jefe_administracion')) && $request->has('area_id')) {
            $query->where('id_area', $request->area_id);
        }

        // Ordenar los resultados
        $fondos = $query->orderBy('fecha_apertura', 'desc')->get();

        return response()->json([
            'message' => 'Fondos de efectivo obtenidos exitosamente.',
            'fondos' => $fondos,
        ]);
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
                        'detallesGastosProyectados:id,id_solicitud_fondo,descripcion_gasto,monto_estimado',
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
     * Elimina un fondo de efectivo.
     * Esta acción debe ser muy restringida, idealmente solo para super_admin y si el fondo está "Cerrado".
     * En un sistema real, la eliminación física de fondos activos es rara y se prefiere el cierre lógico.
     *
     * @param  int  $id_fondo
     * @return \Illuminate\Http\JsonResponse
     */
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
                'trace' => $e->getTraceAsString(), // Para depuración
            ], 500);
        }
    }
}
