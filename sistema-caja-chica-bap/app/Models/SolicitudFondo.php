<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SolicitudFondo extends Model
{
    use HasFactory;
    protected $table = 'solicitudes_fondos';
    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'codigo_solicitud',
        'id_solicitante',
        'id_area',
        'tipo_solicitud',
        'motivo_detalle',
        'monto_solicitado',
        'prioridad',
        'estado',
        'motivo_observacion',
        'motivo_descargo',
        'motivo_rechazo_final',
        'id_revisor_adm',
        'id_aprobador_gerente',
        'id_solicitud_original',
    ];

    // Casteo de atributos a tipos nativos de PHP
    protected $casts = [
        'monto_solicitado' => 'decimal:2',
    ];

    /**
     * Relación: Una solicitud pertenece a un solicitante (Usuario).
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_solicitante');
    }

    /**
     * Relación: Una solicitud pertenece a un área.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'id_area');
    }

    /**
     * Relación: Una solicitud puede ser revisada por un Jefe de Administración (Usuario).
     */
    public function revisorAdm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_revisor_adm');
    }

    /**
     * Relación: Una solicitud puede ser aprobada por un Gerente General (Usuario).
     */
    public function aprobadorGerente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_aprobador_gerente');
    }

    /**
     * Relación: Una solicitud de modificación (Incremento/Decremento/Cierre) pertenece a una solicitud original (auto-referencia).
     */
    public function solicitudOriginal(): BelongsTo
    {
        return $this->belongsTo(SolicitudFondo::class, 'id_solicitud_original');
    }

    /**
     * Relación: Una solicitud de apertura puede tener un fondo efectivo asociado (HasOne).
     * Esto solo aplica a solicitudes de tipo 'Apertura' que han sido 'Aprobadas'.
     */
    public function fondoEfectivo(): HasOne
    {
        return $this->hasOne(FondoEfectivo::class, 'id_solicitud_apertura');
    }

    /**
     * Relación: Una solicitud tiene muchos detalles de gastos proyectados.
     */
    public function detallesGastosProyectados(): HasMany
    {
        return $this->hasMany(DetalleGastoProyectado::class, 'id_solicitud_fondo');
    }

    /**
     * Relación: Una solicitud tiene muchos registros en su historial de estados.
     */
    public function historialEstados(): HasMany
    {
        return $this->hasMany(HistorialEstadoSolicitud::class, 'id_solicitud_fondo');
    }
}

