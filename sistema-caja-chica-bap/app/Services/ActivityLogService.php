<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ActivityLogService
{
    /**
     * Registra una actividad en el log.
     *
     * @param string $actionType El tipo de acción (ej. 'CERRADO', 'EXCEPCION_OTORGADA').
     * @param Model $subject El modelo sobre el cual se realiza la acción.
     * @param array|null $properties Detalles adicionales para el log.
     * @param User|null $user El usuario que realiza la acción (opcional, por defecto el autenticado).
     */
    public function log(string $actionType, Model $subject, ?array $properties = null, ?User $user = null): void
    {
        // Si no se pasa un usuario, se usa el que está autenticado actualmente.
        $user = $user ?? Auth::user();

        ActivityLog::create([
            'user_id'      => $user ? $user->id : null,
            'action_type'  => $actionType,
            'subject_type' => get_class($subject),
            'subject_id'   => $subject->getKey(),
            'properties'   => $properties,
        ]);
    }
}
