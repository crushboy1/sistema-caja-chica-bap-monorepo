<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GastoProyectado extends Model
{
    use HasFactory;

    protected $table = 'gastos_proyectados';
    protected $primaryKey = 'id_gasto_proyectado';

    protected $fillable = [
        'descripcion',
        'activa',
        'id_cuenta_contable',
    ];

    /**
     * Relación: Un Gasto Proyectado pertenece a una Cuenta Contable.
     */
    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'id_cuenta_contable', 'id');
    }

    /**
     * Relación: Un Gasto Proyectado puede estar en muchas Solicitudes de Fondos.
     */
    public function solicitudesFondos(): BelongsToMany
    {
        return $this->belongsToMany(SolicitudFondo::class, 'solicitud_gasto_proyectado', 'gasto_proyectado_id', 'solicitud_fondo_id')
            ->withPivot('monto_estimado') // Importante para acceder al monto
            ->withTimestamps();
    }
}
