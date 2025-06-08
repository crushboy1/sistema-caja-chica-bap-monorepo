<?php
// Comando para generar: php artisan make:model Gasto

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB; // Para la generación de código único

class Gasto extends Model
{
    use HasFactory;

    /**
     * El nombre de la tabla asociada con el modelo.
     *
     * @var string
     */
    protected $table = 'gastos';

    /**
     * Los atributos que se pueden asignar masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'codigo_gasto',
        'id_fondo_efectivo',
        'id_registrador',
        'id_jefe_aprobador',
        'fecha_documento',
        'tipo_documento',
        'serie_documento',
        'correlativo_documento',
        'monto_total',
        'moneda',
        'id_cuenta_contable',
        'glosa',
        'ruta_evidencia',
        'es_declaracion_jurada',
        'estado',
        'motivo_observacion_adm',
    ];
    
    /**
     * El "boot" method del modelo.
     * Se ejecuta cuando el modelo es inicializado.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Se asigna un código único automáticamente antes de crear un nuevo gasto.
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

    // --- RELACIONES ---

    /**
     * Un gasto pertenece a un único Fondo de Efectivo.
     * Relación con la tabla 'fondo_efectivo' (ya existente).
     */
    public function fondoEfectivo()
    {
        // El segundo argumento es la clave foránea en la tabla 'gastos',
        // el tercer argumento es la clave primaria en la tabla 'fondo_efectivo'.
        return $this->belongsTo(FondoEfectivo::class, 'id_fondo_efectivo', 'id_fondo');
    }

    /**
     * Un gasto es registrado por un único Usuario.
     * Relación con la tabla 'users' (ya existente).
     */
    public function registrador()
    {
        return $this->belongsTo(User::class, 'id_registrador');
    }

    /**
     * Un gasto es aprobado por un único Jefe de Área.
     * Relación con la tabla 'users' (ya existente).
     */
    public function jefeAprobador()
    {
        return $this->belongsTo(User::class, 'id_jefe_aprobador');
    }

    /**
     * Un gasto pertenece a una única Cuenta Contable.
     * Relación con la nueva tabla 'cuentas_contables'.
     */
    public function cuentaContable()
    {
        return $this->belongsTo(CuentaContable::class, 'id_cuenta_contable');
    }

    /**
     * Un gasto puede tener múltiples entradas en su historial.
     * Relación con la nueva tabla 'historial_aprobaciones_gastos'.
     */
    public function historial()
    {
        return $this->hasMany(HistorialAprobacionGasto::class, 'id_gasto');
    }
}
