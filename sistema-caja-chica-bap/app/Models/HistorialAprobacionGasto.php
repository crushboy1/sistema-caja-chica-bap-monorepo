<?php
// Comando para generar: php artisan make:model HistorialAprobacionGasto

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialAprobacionGasto extends Model
{
    use HasFactory;
    
    /**
     * El nombre de la tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'historial_aprobaciones_gastos';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_gasto',
        'estado_anterior',
        'estado_nuevo',
        'id_usuario_accion',
        'comentario',
        'fecha_cambio',
        'cambios_realizados',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array
     */
    protected $casts = [
        'cambios_realizados' => 'array',
        'fecha_cambio' => 'datetime',
    ];
    
    // --- RELACIONES ---

    /**
     * Una entrada de historial pertenece a un único Gasto.
     * Relación con la nueva tabla 'gastos'.
     */
    public function gasto()
    {
        return $this->belongsTo(Gasto::class, 'id_gasto');
    }

    /**
     * La acción fue realizada por un único Usuario.
     * Relación con la tabla 'users' (ya existente).
     */
    public function usuarioAccion()
    {
        return $this->belongsTo(User::class, 'id_usuario_accion');
    }
}
