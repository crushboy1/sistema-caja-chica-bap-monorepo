<?php

namespace App\Traits;

use App\Models\Gasto;
use App\Models\HistorialAprobacionGasto;
use Illuminate\Support\Carbon;

trait RegistersHistory
{
    /**
     * Helper para registrar en el historial de manera consistente.
     *
     * @param Gasto $gasto El objeto Gasto afectado.
     * @param string $estadoAnterior El estado anterior del gasto.
     * @param string $estadoNuevo El nuevo estado del gasto.
     * @param int $userId El ID del usuario que realizó la acción.
     * @param string|null $comentario Un comentario opcional sobre la acción.
     * @param array|null $cambios Un array asociativo de cambios realizados (ej. ['campo' => ['anterior' => 'x', 'nuevo' => 'y']]).
     * @return void
     */
    protected function registrarHistorial(Gasto $gasto, string $estadoAnterior, string $estadoNuevo, int $userId, ?string $comentario, ?array $cambios = null): void
    {
        HistorialAprobacionGasto::create([
            'id_gasto' => $gasto->id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'id_usuario_accion' => $userId,
            'comentario' => $comentario,
            'cambios_realizados' => $cambios,
        ]);
    }
}
