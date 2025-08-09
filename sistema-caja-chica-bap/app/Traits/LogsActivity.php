<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait LogsActivity
{
    /**
     * Campos que no deben ser incluidos en el log de cambios
     * (para evitar registrar cambios en campos técnicos)
     */
    protected array $excludeFromActivityLog = [
        'updated_at',
        'remember_token',
        'email_verified_at',
        'password'
    ];

    /**
     * Este es el método "boot" del Trait. Laravel lo ejecuta automáticamente
     * cuando un modelo que usa este Trait es inicializado.
     */
    protected static function bootLogsActivity(): void
    {
        // Se registra un oyente para el evento 'created'
        static::created(function (Model $model) {
            try {
                $model->logActivity('CREADO', $model);
            } catch (\Exception $e) {
                Log::error('Error logging created activity: ' . $e->getMessage(), [
                    'model' => get_class($model),
                    'model_id' => $model->getKey()
                ]);
            }
        });

        // Se registra un oyente para el evento 'updated'
        static::updated(function (Model $model) {
            try {
                // Solo logear si hay cambios significativos
                $changes = $model->getFilteredDirtyAttributes();

                if (!empty($changes)) {
                    $properties = [
                        'old' => array_intersect_key($model->getOriginal(), $changes),
                        'new' => $changes,
                        'changed' => $changes,
                        'descripcion' => $model->generateChangeDescription($changes)
                    ];
                    $model->logActivity('ACTUALIZADO', $model, $properties);
                }
            } catch (\Exception $e) {
                Log::error('Error logging updated activity: ' . $e->getMessage(), [
                    'model' => get_class($model),
                    'model_id' => $model->getKey()
                ]);
            }
        });

        // Se registra un oyente para el evento 'deleted'
        static::deleted(function (Model $model) {
            try {
                $properties = [
                    'deleted_data' => $model->getAttributes(),
                    'descripcion' => 'Se eliminó: ' . ($model->name ?? $model->nombre ?? 'Registro ID: ' . $model->getKey())
                ];
                $model->logActivity('ELIMINADO', $model, $properties);
            } catch (\Exception $e) {
                Log::error('Error logging deleted activity: ' . $e->getMessage(), [
                    'model' => get_class($model),
                    'model_id' => $model->getKey()
                ]);
            }
        });
    }

    /**
     * Método centralizado para crear el registro en la tabla de logs.
     *
     * @param string $actionType
     * @param Model $model
     * @param array|null $properties
     * @return ActivityLog|null
     */
    protected function logActivity(string $actionType, Model $model, ?array $properties = null): ?ActivityLog
    {
        try {
            $user = Auth::user();

            return ActivityLog::create([
                'user_id' => $user ? $user->id : null,
                'action_type' => $actionType,
                'subject_type' => get_class($model),
                'subject_id' => $model->getKey(),
                'properties' => $properties,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create activity log: ' . $e->getMessage(), [
                'action_type' => $actionType,
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'user_id' => Auth::id()
            ]);
            return null;
        }
    }

    /**
     * Log personalizado para acciones específicas del negocio
     *
     * @param string $actionType
     * @param string $descripcion
     * @param array $additionalData
     * @return ActivityLog|null
     */
    public function logCustomActivity(string $actionType, string $descripcion, array $additionalData = []): ?ActivityLog
    {
        $properties = array_merge($additionalData, [
            'descripcion' => $descripcion,
            'custom_action' => true
        ]);

        return $this->logActivity($actionType, $this, $properties);
    }

    /**
     * Obtiene los cambios filtrados, excluyendo campos técnicos
     *
     * @return array
     */
    protected function getFilteredDirtyAttributes(): array
    {
        $dirty = $this->getDirty();
        $excludeFields = array_merge($this->excludeFromActivityLog, $this->getHidden());

        return array_diff_key($dirty, array_flip($excludeFields));
    }

    /**
     * Genera una descripción legible de los cambios realizados
     *
     * @param array $changes
     * @return string
     */
    protected function generateChangeDescription(array $changes): string
    {
        if (empty($changes)) {
            return 'No se realizaron cambios significativos';
        }

        $fieldNames = array_keys($changes);
        $friendlyNames = array_map([$this, 'getFieldFriendlyName'], $fieldNames);

        if (count($friendlyNames) === 1) {
            return "Se modificó: {$friendlyNames[0]}";
        } elseif (count($friendlyNames) <= 3) {
            return "Se modificaron: " . implode(', ', $friendlyNames);
        } else {
            $firstThree = array_slice($friendlyNames, 0, 3);
            $remaining = count($friendlyNames) - 3;
            return "Se modificaron: " . implode(', ', $firstThree) . " y {$remaining} campos más";
        }
    }

    /**
     * Convierte nombres de campos técnicos a nombres amigables
     *
     * @param string $field
     * @return string
     */
    protected function getFieldFriendlyName(string $field): string
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

    /**
     * Deshabilita temporalmente el logging de actividades
     */
    public static function withoutActivityLogging(callable $callback)
    {
        // Crear una bandera temporal
        static::$loggingDisabled = true;

        try {
            return $callback();
        } finally {
            static::$loggingDisabled = false;
        }
    }

    /**
     * Verifica si el logging está habilitado
     */
    protected static function shouldLogActivity(): bool
    {
        return !isset(static::$loggingDisabled) || !static::$loggingDisabled;
    }

    /**
     * Obtiene el historial de cambios para este modelo
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function activityHistory()
    {
        return ActivityLog::where('subject_type', get_class($this))
            ->where('subject_id', $this->getKey())
            ->with('user:id,name,last_name')
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
