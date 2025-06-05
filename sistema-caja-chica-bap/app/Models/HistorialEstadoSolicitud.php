<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialEstadoSolicitud extends Model
{
    use HasFactory;

    protected $table = 'historial_estados_solicitud';
    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'id_solicitud_fondo',
        'estado_anterior',
        'estado_nuevo',
        'observaciones',
        'id_usuario_accion',
        'fecha_cambio',
    ];

    // Casteo de atributos
    protected $casts = [
        'fecha_cambio' => 'datetime',
    ];

    /**
     * Relación: Un registro de historial pertenece a una solicitud de fondo.
     */
    public function solicitudFondo(): BelongsTo
    {
        return $this->belongsTo(SolicitudFondo::class, 'id_solicitud_fondo');
    }

    /**
     * Relación: Un registro de historial es realizado por un usuario.
     */
    public function usuarioAccion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_accion');
    }
}

