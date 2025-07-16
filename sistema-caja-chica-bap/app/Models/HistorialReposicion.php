<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialReposicion extends Model
{
    use HasFactory;

    protected $table = 'historial_reposiciones';

    public $timestamps = true;

    protected $fillable = [
        'id_fondo_efectivo',
        'id_usuario_accion',
        'monto_repuesto',
        'saldo_anterior',
        'saldo_nuevo',
        'comentario',
        'ruta_comprobante',
        'fecha_reposicion',
    ];

    protected $casts = [
        'monto_repuesto' => 'decimal:2',
        'saldo_anterior' => 'decimal:2',
        'saldo_nuevo' => 'decimal:2',
        'fecha_reposicion' => 'datetime',
    ];

    // --- RELACIONES ---

    /**
     * Un registro de historial pertenece a un FondoEfectivo.
     */
    public function fondoEfectivo()
    {
        return $this->belongsTo(FondoEfectivo::class, 'id_fondo_efectivo', 'id_fondo');
    }

    /**
     * Un registro de historial es ejecutado por un Usuario.
     */
    public function usuarioAccion()
    {
        return $this->belongsTo(User::class, 'id_usuario_accion');
    }
}
