<?php
// Comando para generar: php artisan make:model Gasto

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Gasto extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'gastos';

    /**
     * The attributes that are mass assignable.
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
        'moneda',
        'tipo_cambio_referencial',
        'tipo_cambio', // NUEVO: Tipo de cambio para gastos en USD
        'monto_final_pen', // NUEVO: Monto final en PEN para contabilidad
        'glosa',
        'pertenece_proyecto',
        'comentario',
        'ruta_evidencia',
        'es_declaracion_jurada',
        'estado',
        'motivo_observacion_adm',
        'motivo_rechazo',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'fecha_documento' => 'date',
        'monto_total' => 'decimal:2',
        'tipo_cambio_referencial' => 'decimal:4',
        'tipo_cambio' => 'decimal:4',
        'monto_final_pen' => 'decimal:2',
        'pertenece_proyecto' => 'boolean',
        'es_declaracion_jurada' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['evidencia_url'];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Automatically assign a unique code before creating a new expense.
        static::creating(function ($gasto) {
            if (empty($gasto->codigo_gasto)) {
                $gasto->codigo_gasto = self::generateUniqueGastoCode();
            }
        });
    }

    /**
     * Generate a unique code for the expense (e.g., GTO-00001).
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

    // --- RELATIONSHIPS ---

    public function fondoEfectivo()
    {
        return $this->belongsTo(FondoEfectivo::class, 'id_fondo_efectivo', 'id_fondo');
    }

    public function registrador()
    {
        return $this->belongsTo(User::class, 'id_registrador');
    }

    public function jefeAprobador()
    {
        return $this->belongsTo(User::class, 'id_jefe_aprobador');
    }
    public function validadorAdm()
    {
        return $this->belongsTo(User::class, 'id_validador_adm');
    }

    public function cuentaContable()
    {
        return $this->belongsTo(CuentaContable::class, 'id_cuenta_contable');
    }

    public function historial()
    {
        return $this->hasMany(HistorialAprobacionGasto::class, 'id_gasto');
    }


    // --- ACCESSORS & MUTATORS ---

    /**
     * Get the full URL for the evidence file.
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
