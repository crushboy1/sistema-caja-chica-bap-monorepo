<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialMovimientoFondo extends Model
{
    use HasFactory;

    protected $table = 'historial_movimientos_fondos';

    public $timestamps = true;

    protected $fillable = [
        'id_fondo_efectivo',
        'id_usuario_accion',
        'tipo_movimiento',
        'monto_movimiento',
        'saldo_anterior',
        'saldo_nuevo',
        'comentario',
        'ruta_comprobante',
        'fecha_movimiento',
    ];

    protected $casts = [
        'monto_movimiento' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_nuevo' => 'decimal:2',
        'fecha_movimiento' => 'datetime',
    ];

    // --- RELACIONES ---

    /**
     * Un registro de historial pertenece a un FondoEfectivo.
     */
    public function fondoEfectivo(): BelongsTo
    {
        return $this->belongsTo(FondoEfectivo::class, 'id_fondo_efectivo', 'id_fondo');
    }

    /**
     * Un registro de historial es ejecutado por un Usuario.
     */
    public function usuarioAccion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_accion');
    }
}
