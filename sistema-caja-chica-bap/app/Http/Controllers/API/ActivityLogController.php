<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Exports\EnhancedActivityLogExport;
use App\Traits\FiltersActivityLog;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ActivityLogController extends Controller
{
    use FiltersActivityLog;

    /**
     * Muestra una lista paginada y filtrable de los registros de actividad.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Reutiliza la lógica de filtrado desde el Trait mejorado
            $query = $this->buildFilteredQuery($request);

            // Aplica eager loading, ordenamiento y paginación
            $logs = $query->with(['user:id,name,last_name'])
                ->latest('created_at')
                ->paginate($request->get('per_page', 15));

            // Transforma los datos para una respuesta más limpia
            $logs->getCollection()->transform(function ($log) {
                return [
                    'id' => $log->id,
                    'fecha' => $log->created_at->format('d/m/Y H:i:s'),
                    'usuario' => $log->user
                        ? trim($log->user->name . ' ' . $log->user->last_name)
                        : 'Sistema Automático',
                    'accion' => $this->formatActionTypeForApi($log->action_type),
                    'modulo' => $this->getModelFriendlyName($log->subject_type),
                    'descripcion' => $this->generateDescriptionForApi($log),
                    'detalles_disponibles' => !empty($log->properties),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $logs,
                'filtros_aplicados' => $this->getAppliedFilters($request),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cargar logs de actividad: ' . $e->getMessage(), [
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar los registros de actividad',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtiene los detalles completos de un log específico
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $log = $this->buildFilteredQuery($request)
                ->with(['user:id,name,last_name'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $log->id,
                    'fecha' => $log->created_at->format('d/m/Y H:i:s'),
                    'usuario' => $log->user
                        ? trim($log->user->name . ' ' . $log->user->last_name)
                        : 'Sistema Automático',
                    'accion' => $this->formatActionTypeForApi($log->action_type),
                    'modulo' => $this->getModelFriendlyName($log->subject_type),
                    'descripcion' => $this->generateDescriptionForApi($log),
                    'propiedades' => $this->formatPropertiesForApi($log->properties),
                    'modelo_afectado' => [
                        'tipo' => class_basename($log->subject_type),
                        'id' => $log->subject_id,
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registro no encontrado'
            ], 404);
        }
    }

    /**
     * Exporta los registros de actividad a Excel
     */
    public function export(Request $request)
    {
        try {
            // Usar la validación mejorada del trait
            $filters = $request->validate([
                'fecha_inicio' => 'nullable|date_format:Y-m-d',
                'fecha_fin'    => 'nullable|date_format:Y-m-d|after_or_equal:fecha_inicio',
                'user_id'      => 'nullable|integer|exists:users,id',
                'subject_type' => 'nullable|string',
                'action_type'  => 'nullable|string|in:CREADO,ACTUALIZADO,ELIMINADO,PERIODO_CERRADO,PERIODO_REABIERTO,EXCEPCION_OTORGADA,EXCEPCION_REVOCADA',
            ]);

            // Validar que no se esté exportando demasiados registros
            $query = $this->buildFilteredQuery($request);
            $totalRecords = $query->count();

            if ($totalRecords > 10000) {
                return response()->json([
                    'success' => false,
                    'message' => 'La consulta contiene demasiados registros (' . number_format($totalRecords) . '). Por favor, aplica filtros más específicos.',
                    'total_records' => $totalRecords
                ], 422);
            }

            if ($totalRecords === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron registros con los filtros aplicados.'
                ], 422);
            }

            // Generar nombre del archivo descriptivo
            $fileName = $this->generateExportFileName($filters);

            // Log de la exportación
            Log::info('Exportación de logs de auditoría iniciada', [
                'user_id' => auth()->id(),
                'filters' => $filters,
                'total_records' => $totalRecords,
                'filename' => $fileName
            ]);

            return Excel::download(new EnhancedActivityLogExport($filters), $fileName);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de filtros inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en exportación de logs: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte'
            ], 500);
        }
    }

    /**
     * Obtiene estadísticas de los logs de actividad con caché
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            // Usar caché para mejorar performance (5 minutos)
            $cacheKey = 'activity_logs_stats_' . md5(serialize($request->all()));

            $stats = Cache::remember($cacheKey, 300, function () use ($request) {
                return $this->getActivityLogStats($request);
            });

            // Clonar la consulta para no mutarla
            $queryClone = $this->buildFilteredQuery($request);

            $additionalStats = [
                'actividad_hoy' => (clone $queryClone)->whereDate('created_at', today())->count(),
                'actividad_semana' => (clone $queryClone)->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ])->count(),
                'usuarios_activos' => (clone $queryClone)->distinct('user_id')
                    ->whereNotNull('user_id')
                    ->count('user_id'),
            ];

            $completeStats = array_merge($stats, $additionalStats);

            // Formatear para la API
            $formattedStats = [
                'resumen' => [
                    'total_registros' => $completeStats['total_registros'],
                    'actividad_hoy' => $completeStats['actividad_hoy'],
                    'actividad_semana' => $completeStats['actividad_semana'],
                    'usuarios_activos' => $completeStats['usuarios_activos'],
                ],
                // SE APLICA LA CORRECCIÓN AQUÍ (use ($completeStats))
                'por_accion' => collect($completeStats['por_accion'])->map(function ($count, $action) use ($completeStats) {
                    return [
                        'accion' => $this->formatActionTypeForApi($action),
                        'cantidad' => $count,
                        'porcentaje' => $completeStats['total_registros'] > 0
                            ? round(($count / $completeStats['total_registros']) * 100, 1)
                            : 0
                    ];
                })->values(),
                // SE APLICA LA CORRECCIÓN AQUÍ (use ($completeStats))
                'por_modulo' => collect($completeStats['por_modelo'])->map(function ($count, $model) use ($completeStats) {
                    return [
                        'modulo' => $this->getModelFriendlyName($model),
                        'cantidad' => $count,
                        'porcentaje' => $completeStats['total_registros'] > 0
                            ? round(($count / $completeStats['total_registros']) * 100, 1)
                            : 0
                    ];
                })->values(),
                'rango_fechas' => $completeStats['rango_fechas'],
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedStats,
                'filtros_aplicados' => $this->getAppliedFilters($request),
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cargar estadísticas de auditoría: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar estadísticas'
            ], 500);
        }
    }

    /**
     * Obtiene opciones para los filtros (usuarios activos, tipos de modelo, etc.)
     */
    public function filterOptions(): JsonResponse
    {
        try {
            $options = Cache::remember('activity_log_filter_options', 600, function () {
                return [
                    'usuarios' => $this->getActiveUsers(),
                    'tipos_modelo' => $this->getAvailableSubjectTypes(),
                    'tipos_accion' => [
                        ['value' => 'CREADO', 'label' => 'Creaciones'],
                        ['value' => 'ACTUALIZADO', 'label' => 'Modificaciones'],
                        ['value' => 'ELIMINADO', 'label' => 'Eliminaciones'],
                        ['value' => 'PERIODO_CERRADO', 'label' => 'Cierres de Período'],
                        ['value' => 'PERIODO_REABIERTO', 'label' => 'Reaperturas'],
                        ['value' => 'EXCEPCION_OTORGADA', 'label' => 'Excepciones Otorgadas'],
                        ['value' => 'EXCEPCION_REVOCADA', 'label' => 'Excepciones Revocadas'],
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $options
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cargar opciones de filtro: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar opciones'
            ], 500);
        }
    }

    /**
     * Formatea el tipo de acción para la API
     */
    private function formatActionTypeForApi(string $actionType): string
    {
        $actions = [
            'CREADO' => 'Creado',
            'ACTUALIZADO' => 'Actualizado',
            'ELIMINADO' => 'Eliminado',
            'PERIODO_CERRADO' => 'Período Cerrado',
            'PERIODO_REABIERTO' => 'Período Reabierto',
            'EXCEPCION_OTORGADA' => 'Excepción Otorgada',
            'EXCEPCION_REVOCADA' => 'Excepción Revocada'
        ];

        return $actions[$actionType] ?? $actionType;
    }

    /**
     * Genera una descripción para la API
     */
    private function generateDescriptionForApi($log): string
    {
        if (isset($log->properties['descripcion'])) {
            return $log->properties['descripcion'];
        }

        $modelName = $this->getModelFriendlyName($log->subject_type);

        switch ($log->action_type) {
            case 'CREADO':
                return "Nuevo registro creado en {$modelName}";
            case 'ACTUALIZADO':
                $changedCount = isset($log->properties['changed']) ? count($log->properties['changed']) : 0;
                return $changedCount > 0
                    ? "Se modificaron {$changedCount} campos en {$modelName}"
                    : "Registro actualizado en {$modelName}";
            case 'ELIMINADO':
                return "Registro eliminado de {$modelName}";
            default:
                return "{$this->formatActionTypeForApi($log->action_type)} en {$modelName}";
        }
    }

    /**
     * Formatea las propiedades para la API
     */
    private function formatPropertiesForApi($properties): array
    {
        if (!$properties) {
            return [];
        }

        $formatted = [];

        if (isset($properties['changed']) && is_array($properties['changed'])) {
            $formatted['cambios'] = [];
            foreach ($properties['changed'] as $field => $newValue) {
                $oldValue = $properties['old'][$field] ?? null;
                $formatted['cambios'][] = [
                    'campo' => $this->getFieldFriendlyName($field),
                    'valor_anterior' => $oldValue,
                    'valor_nuevo' => $newValue,
                ];
            }
        }

        if (isset($properties['descripcion'])) {
            $formatted['descripcion'] = $properties['descripcion'];
        }

        return $formatted;
    }

    /**
     * Genera nombre descriptivo para el archivo de exportación
     */
    private function generateExportFileName(array $filters): string
    {
        $parts = ['auditoria'];

        if (!empty($filters['fecha_inicio'])) {
            $parts[] = 'desde_' . str_replace('-', '', $filters['fecha_inicio']);
        }

        if (!empty($filters['fecha_fin'])) {
            $parts[] = 'hasta_' . str_replace('-', '', $filters['fecha_fin']);
        }

        $parts[] = now()->format('Y-m-d_H-i-s');

        return implode('_', $parts) . '.xlsx';
    }

    /**
     * Obtiene los filtros aplicados para mostrar en la respuesta
     */
    private function getAppliedFilters(Request $request): array
    {
        $applied = [];

        if ($request->filled('fecha_inicio')) {
            $applied['fecha_inicio'] = $request->fecha_inicio;
        }

        if ($request->filled('fecha_fin')) {
            $applied['fecha_fin'] = $request->fecha_fin;
        }

        if ($request->filled('user_id')) {
            $applied['usuario'] = $request->user_id;
        }

        if ($request->filled('subject_type')) {
            $applied['modulo'] = $this->getModelFriendlyName($request->subject_type);
        }

        if ($request->filled('action_type')) {
            $applied['accion'] = $this->formatActionTypeForApi($request->action_type);
        }

        return $applied;
    }

    /**
     * Convierte el nombre de clase a un nombre amigable (reutiliza del trait mejorado)
     */
    private function getFieldFriendlyName(string $field): string
    {
        $friendlyNames = [
            'name' => 'Nombre',
            'nombre' => 'Nombre',
            'descripcion' => 'Descripción',
            'codigo' => 'Código',
            'activo' => 'Estado',
            'monto' => 'Monto',
            'fecha' => 'Fecha',
            'estado' => 'Estado',
            'id_proyecto' => 'Proyecto',
            'id_cuenta_contable' => 'Cuenta Contable',
            'created_at' => 'Fecha de Creación',
            'updated_at' => 'Fecha de Actualización',
        ];

        return $friendlyNames[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }
}
