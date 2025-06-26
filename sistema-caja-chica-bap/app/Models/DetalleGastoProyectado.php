<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /**
     * NUEVA RELACIÓN INVERSA: Un detalle de gasto proyectado puede tener un gasto declarado asociado.
     * Esto define la relación uno-a-uno (o uno-a-cero) con un Gasto.
     * Nos será muy útil para filtrar y mostrar al usuario solo las proyecciones
     * que todavía no han sido declaradas. Por ejemplo: $proyeccion->gastoDeclarado
     * Si el resultado es null, significa que la proyección está pendiente de declarar.
     */
    public function gastoDeclarado(): HasOne
    {
        return $this->hasOne(Gasto::class, 'detalle_gasto_proyectado_id');
    }
}
