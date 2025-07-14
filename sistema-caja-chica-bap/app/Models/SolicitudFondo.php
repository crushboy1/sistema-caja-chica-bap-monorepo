<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany; 
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;

class SolicitudFondo extends Model
{
    use HasFactory;
    protected $table = 'solicitudes_fondos';
    
    protected $fillable = [
        'codigo_solicitud',
        'id_solicitante',
        'id_area',
        'tipo_solicitud',
        'tipo_fondo_solicitado',
        'id_proyecto',
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
        'edit_count',
        'historial_cambios',
    ];

    protected $casts = [
        'monto_solicitado' => 'decimal:2',
        'historial_cambios' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SolicitudFondo $solicitud) {
            if (is_null($solicitud->codigo_solicitud)) {
                $solicitud->loadMissing('area', 'solicitudOriginal.fondoEfectivo');
                $generator = new CodeGeneratorService();
                $solicitud->codigo_solicitud = $generator->generateForSolicitud($solicitud);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relaciones de Eloquent
    |--------------------------------------------------------------------------
    */

    /**
     * Relación: Una solicitud tiene muchos Gastos Proyectados a través de la tabla pivote.
     * Esta es la nueva relación que reemplaza a la antigua 'detallesGastosProyectados'.
     */
    public function gastosProyectados(): BelongsToMany
    {
        return $this->belongsToMany(GastoProyectado::class, 'solicitud_gasto_proyectado', 'solicitud_fondo_id', 'gasto_proyectado_id')
                    ->withPivot('monto_estimado') // Importante para poder acceder al monto estimado guardado en la tabla pivote.
                    ->withTimestamps();
    }

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
        // Se especifica la clave primaria de la tabla 'areas' para evitar ambigüedades.
        return $this->belongsTo(Area::class, 'id_area', 'id');
    }
    
    /**
     * Relación opcional: Una solicitud puede estar asociada a un proyecto.
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
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
     * Relación: Una solicitud de modificación pertenece a una solicitud original.
     */
    public function solicitudOriginal(): BelongsTo
    {
        return $this->belongsTo(SolicitudFondo::class, 'id_solicitud_original');
    }

    /**
     * Relación: Una solicitud de apertura puede tener un fondo efectivo asociado.
     */
    public function fondoEfectivo(): HasOne
    {
        return $this->hasOne(FondoEfectivo::class, 'id_solicitud_apertura');
    }

    /**
     * Relación: Una solicitud tiene muchos registros en su historial de estados.
     */
    public function historialEstados(): HasMany
    {
        return $this->hasMany(HistorialEstadoSolicitud::class, 'id_solicitud_fondo');
    }

    /*
    | Métodos Obsoletos (Comentados para Referencia)
    */

    /**
     * COMENTARIO: El método 'handleEdit' ha quedado obsoleto con la nueva lógica.
     * La sincronización de la tabla pivote (usando el método `sync()` de Eloquent)
     * se manejará de forma más limpia y directa en el `SolicitudFondoController`.
     *
     * public function handleEdit(array $validatedData, User $user): self
     * { ... }
     */

    // Métodos de Ayuda (Funcionales)
    /**
     * Registra una nueva entrada en el historial de estados de la solicitud.
     */
    public function registrarEnHistorial(string $nuevoEstado, string $observaciones, int $userId): void
    {
        $this->historialEstados()->create([
            'estado_anterior' => $this->estado,
            'estado_nuevo' => $nuevoEstado,
            'observaciones' => $observaciones,
            'id_usuario_accion' => $userId,
            'fecha_cambio' => now(),
        ]);
    }
}
