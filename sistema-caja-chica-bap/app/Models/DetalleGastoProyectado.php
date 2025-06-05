<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetalleGastoProyectado extends Model
{
    use HasFactory;

    // Define el nombre de la tabla
    protected $table = 'detalle_gastos_proyectados';

    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'id_solicitud_fondo',
        'descripcion_gasto',
        'monto_estimado',
    ];

    // Casteo de atributos
    protected $casts = [
        'monto_estimado' => 'decimal:2',
    ];

    /**
     * Relación: Un detalle de gasto proyectado pertenece a una solicitud de fondo.
     */
    public function solicitudFondo(): BelongsTo
    {
        return $this->belongsTo(SolicitudFondo::class, 'id_solicitud_fondo');
    }
}

