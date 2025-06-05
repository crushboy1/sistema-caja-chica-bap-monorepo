<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FondoEfectivo extends Model
{
    use HasFactory;
    protected $table = 'fondo_efectivo';
    protected $primaryKey = 'id_fondo';
    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'codigo_fondo', 
        'monto_aprobado',
        'fecha_apertura',
        'estado',
        'fecha_cierre',
        'motivo_cierre',
        'id_solicitud_apertura',
        'id_responsable',
        'id_area',
    ];

    // Casteo de atributos
    protected $casts = [
        'monto_aprobado' => 'decimal:2',
        'fecha_apertura' => 'date',
        'fecha_cierre' => 'date',
    ];

    /**
     * Relación: Un fondo efectivo fue originado por una solicitud de apertura.
     */
    public function solicitudApertura(): BelongsTo
    {
        return $this->belongsTo(SolicitudFondo::class, 'id_solicitud_apertura');
    }

    /**
     * Relación: Un fondo efectivo tiene un usuario responsable.
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_responsable');
    }

    /**
     * Relación: Un fondo efectivo pertenece a un área.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'id_area');
    }
}

