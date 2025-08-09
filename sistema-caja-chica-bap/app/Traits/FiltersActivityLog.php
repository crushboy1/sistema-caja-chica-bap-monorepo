<?php

namespace App\Traits;

use App\Models\ActivityLog;
use App\Models\User; 
use Illuminate\Http\Request;
use Carbon\Carbon;

trait FiltersActivityLog
{
    /**
     * Construye una consulta filtrada para los logs de actividad.
     *
     * @param Request $request
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function buildFilteredQuery(Request $request)
    {
        // Validación mejorada con mensajes personalizados
        $validated = $request->validate([
            'fecha_inicio' => 'nullable|date_format:Y-m-d',
            'fecha_fin'    => 'nullable|date_format:Y-m-d|after_or_equal:fecha_inicio',
            'user_id'      => 'nullable|integer|exists:users,id',
            'subject_type' => 'nullable|string',
            'action_type'  => 'nullable|string|in:CREADO,ACTUALIZADO,ELIMINADO,PERIODO_CERRADO,PERIODO_REABIERTO,EXCEPCION_OTORGADA,EXCEPCION_REVOCADA', // Validación de tipos válidos
        ], [
            'fecha_fin.after_or_equal' => 'La fecha final debe ser posterior o igual a la fecha de inicio.',
            'user_id.exists' => 'El usuario seleccionado no existe.',
            'action_type.in' => 'El tipo de acción no es válido.',
        ]);

        $query = ActivityLog::query();

        // Filtro por rango de fechas (mejorado con manejo de tiempo)
        if (!empty($validated['fecha_inicio'])) {
            $fechaInicio = Carbon::parse($validated['fecha_inicio'])->startOfDay();
            $query->where('created_at', '>=', $fechaInicio);
        }

        if (!empty($validated['fecha_fin'])) {
            $fechaFin = Carbon::parse($validated['fecha_fin'])->endOfDay();
            $query->where('created_at', '<=', $fechaFin);
        }

        // Filtro por usuario
        if (!empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        // Filtro por tipo de modelo (mejorado)
        if (!empty($validated['subject_type'])) {
            $subjectType = $this->normalizeSubjectType($validated['subject_type']);
            $query->where('subject_type', $subjectType);
        }

        // Filtro por tipo de acción
        if (!empty($validated['action_type'])) {
            $query->where('action_type', $validated['action_type']);
        }

        return $query;
    }

    /**
     * Normaliza el tipo de modelo para asegurar el formato correcto
     *
     * @param string $subjectType
     * @return string
     */
    private function normalizeSubjectType(string $subjectType): string
    {
        // Si ya viene con el namespace completo, lo devolvemos tal como está
        if (strpos($subjectType, 'App\\Models\\') === 0) {
            return $subjectType;
        }

        // Si no, agregamos el namespace
        return 'App\\Models\\' . $subjectType;
    }

    /**
     * Obtiene estadísticas básicas de los logs filtrados
     *
     * @param Request $request
     * @return array
     */
    private function getActivityLogStats(Request $request): array
    {
        $query = $this->buildFilteredQuery($request);

        return [
            'total_registros' => $query->count(),
            'por_accion' => $query->selectRaw('action_type, COUNT(*) as total')
                ->groupBy('action_type')
                ->pluck('total', 'action_type')
                ->toArray(),
            'por_modelo' => $query->selectRaw('subject_type, COUNT(*) as total')
                ->groupBy('subject_type')
                ->pluck('total', 'subject_type')
                ->toArray(),
            'rango_fechas' => [
                'desde' => $query->min('created_at'),
                'hasta' => $query->max('created_at'),
            ]
        ];
    }

    /**
     * Obtiene los tipos de modelos disponibles para filtrar
     *
     * @return array
     */
    private function getAvailableSubjectTypes(): array
    {
        return ActivityLog::select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->map(function ($type) {
                return [
                    'value' => $type,
                    'label' => $this->getModelFriendlyName($type)
                ];
            })
            ->toArray();
    }

    /**
     * Obtiene usuarios que han realizado actividades
     *
     * @return array
     */
    private function getActiveUsers(): array
    {
        // Esta consulta es más directa y performante.
        // Selecciona solo los usuarios que existen en la columna user_id de la tabla activity_logs.
        return User::whereIn('id', ActivityLog::select('user_id')->distinct()->whereNotNull('user_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'last_name'])
            ->map(function ($user) {
                return [
                    'value' => $user->id,
                    'label' => trim($user->name . ' ' . $user->last_name)
                ];
            })
            ->toArray();
    }

    /**
     * Convierte el nombre de clase a un nombre amigable
     *
     * @param string $subjectType
     * @return string
     */
    private function getModelFriendlyName(string $subjectType): string
    {
        $modelNames = [
            'App\\Models\\Proyecto' => 'Gestión de Proyectos',
            'App\\Models\\GastoProyectado' => 'Presupuesto y Gastos',
            'App\\Models\\CuentaContable' => 'Plan de Cuentas',
            'App\\Models\\CierreMensual' => 'Control de Períodos',
            'App\\Models\\ExcepcionCierre' => 'Manejo de Excepciones',
        ];

        return $modelNames[$subjectType] ?? class_basename($subjectType);
    }
}
