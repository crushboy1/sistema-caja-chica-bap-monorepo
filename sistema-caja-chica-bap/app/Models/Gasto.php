<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Gasto extends Model
{
    use HasFactory;

    /**
     * La tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'gastos';

    /**
     * Los atributos que son asignables en masa.
     * Se han añadido los nuevos campos para el flujo de observación.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'codigo_gasto',
        'id_fondo_efectivo',
        'id_registrador',
        'id_jefe_aprobador',
        'id_validador_adm',
        'id_cuenta_contable',
        'fecha_documento',
        'tipo_documento',
        'serie_documento',
        'correlativo_documento',
        'monto_total',
        'monto_proyectado_original',
        'moneda',
        'id_gasto_proyectado',
        'glosa',
        'comentario',
        'ruta_evidencia',
        'es_declaracion_jurada',
        'estado',
        'motivo_observacion_adm',
        'motivo_rechazo',
        'id_dj_consolidada',
        'id_observador_adm',
        'comentario_subsanacion',
    ];

    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_documento' => 'date',
        'monto_total' => 'decimal:2',
        'es_declaracion_jurada' => 'boolean',
    ];

    /**
     * Los "accessors" para añadir al array del modelo.
     *
     * @var array
     */
    protected $appends = ['evidencia_url'];

    /**
     * El método "booted" del modelo.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gasto) {
            if (empty($gasto->codigo_gasto)) {
                $gasto->codigo_gasto = self::generateUniqueGastoCode();
            }
        });
    }

    /**
     * Genera un código único para el gasto (ej. GTO-00001).
     *
     * @return string
     */
    public static function generateUniqueGastoCode()
    {
        $prefix = 'GTO-';
        $lastGasto = DB::table('gastos')->orderBy('id', 'desc')->first();
        $number = $lastGasto ? intval(substr($lastGasto->codigo_gasto, strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
    }

    /*
    |--------------------------------------------------------------------------
    | RELACIONES DE ELOQUENT
    |--------------------------------------------------------------------------
    */

    /**
     * Un gasto pertenece a un tipo de Gasto Proyectado del catálogo.
     */
    public function gastoProyectado(): BelongsTo
    {
        return $this->belongsTo(GastoProyectado::class, 'id_gasto_proyectado', 'id_gasto_proyectado');
    }

    public function fondoEfectivo(): BelongsTo
    {
        // Se asegura que los nombres de las claves sean explícitos para evitar ambigüedades.
        return $this->belongsTo(FondoEfectivo::class, 'id_fondo_efectivo', 'id_fondo');
    }

    public function registrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_registrador');
    }

    public function jefeAprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_jefe_aprobador');
    }

    public function validadorAdm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_validador_adm');
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'id_cuenta_contable');
    }

    public function historialAprobaciones(): HasMany
    {
        // Ahora busca la clase correcta "HistorialAprobacionGasto"
        return $this->hasMany(HistorialAprobacionGasto::class, 'id_gasto');
    }

    // --- NUEVAS RELACIONES ---

    /**
     * El administrador que observó el gasto.
     */
    public function observadorAdm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_observador_adm');
    }

    /**
     * La Declaración Jurada consolidada a la que puede pertenecer este gasto.
     */
    public function djConsolidada(): BelongsTo
    {
        return $this->belongsTo(DjConsolidada::class, 'id_dj_consolidada', 'id_dj_consolidada');
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    |  Los scopes permiten encapsular y reutilizar lógica de consulta.
    */
    public function scopeReadyForConsolidation(Builder $query, int $userId): Builder
    {
        return $query->whereNull('id_dj_consolidada')
            ->where('id_registrador', $userId)
            ->whereIn('estado', ['Pendiente de Aprobación', 'Pendiente de Validación Contable']);
    }
    /*
    |--------------------------------------------------------------------------
    | ACCESSORS & MUTATORS
    |--------------------------------------------------------------------------
    */

    /**
     * Obtiene la URL completa del archivo de evidencia.
     *
     * @return string|null
     */
    public function getEvidenciaUrlAttribute()
    {
        if ($this->ruta_evidencia) {
            return Storage::url($this->ruta_evidencia);
        }
        return null;
    }
}
