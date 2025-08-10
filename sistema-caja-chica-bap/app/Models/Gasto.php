<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use App\Services\CodeGeneratorService;

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
        'monto_excedido_al_registrar',
        'saldo_disponible_al_registrar',
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
        'fecha_limite_rendicion',
        'fecha_rendicion',
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
        'monto_excedido_al_registrar' => 'decimal:2',
        'saldo_disponible_al_registrar' => 'decimal:2',
        'fecha_limite_rendicion' => 'date',
        'fecha_rendicion' => 'date',
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
    protected static function booted(): void
    {
        static::creating(function (Gasto $gasto) {
            // Verificamos si el código no ha sido asignado previamente.
            if (is_null($gasto->codigo_gasto)) {
                // Es crucial cargar las relaciones necesarias ANTES de pasar el modelo al servicio.
                // El servicio necesita acceder a $gasto->fondoEfectivo->area.
                $gasto->loadMissing('fondoEfectivo.area');
                // Instanciamos y usamos nuestro servicio centralizado.
                $generator = new CodeGeneratorService();
                $gasto->codigo_gasto = $generator->generateForGasto($gasto);
            }
        });
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
        // Definimos los estados válidos para que un gasto de tipo DJ pueda ser seleccionado para una nueva consolidación.
        $validStates = [
            'Observado',
            'Pendiente de Aprobación',
            'Pendiente de Validación Contable'
        ];

        return $query->whereNull('id_dj_consolidada')
            ->where('id_registrador', $userId)
            ->where('es_declaracion_jurada', true) // Aseguramos que solo se puedan seleccionar gastos que son DJ
            ->whereIn('estado', $validStates);
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

    // Calcula el monto por el cual un gasto excedería el saldo disponible de su proyección.
    // Devuelve el monto excedido o null si no excede.
    public static function calculateExceededAmountAndAvailableBalance(
        float $currentGastoMonto,
        int $gastoProyectadoId,
        int $fondoEfectivoId,
        float $originalProjectedAmount,
        string $fechaDocumentoActual,
        ?int $excludeGastoId = null
    ): array {
        // Sumar los montos de todos los gastos existentes asociados a esta misma proyección
        // y fondo, excluyendo el gasto actual si se está actualizando.
        // Solo se consideran gastos que ya están en el flujo (no observados ni rechazados)
        // ya que son los que realmente "consumen" del saldo de la proyección.
        // 1. Determinar el rango del mes actual basado en la fecha del documento.
        $fecha = Carbon::parse($fechaDocumentoActual);
        $inicioMes = $fecha->startOfMonth()->toDateString();
        $finMes = $fecha->endOfMonth()->toDateString();
        // 2. Sumar los montos de los gastos existentes DENTRO DEL MISMO PERÍODO MENSUAL.
        $queryGastosExistentes = Gasto::where('id_gasto_proyectado', $gastoProyectadoId)
            ->where('id_fondo_efectivo', $fondoEfectivoId)
            ->whereNotIn('estado', ['Observado', 'Rechazado', 'Repuesto'])
            ->whereBetween('fecha_documento', [$inicioMes, $finMes]);

        if ($excludeGastoId) {
            $queryGastosExistentes->where('id', '!=', $excludeGastoId);
        }
        $gastosAcumuladosPrevios = $queryGastosExistentes->sum('monto_total');
        // Calcular el saldo disponible actual de la proyección
        $saldoDisponible = round($originalProjectedAmount - $gastosAcumuladosPrevios, 2);
        $montoExcedido = 0.00;
        if ($currentGastoMonto > $saldoDisponible) {
            $montoExcedido = round($currentGastoMonto - $saldoDisponible, 2);
        }
        // El saldo disponible que se guarda es el que realmente quedaba antes de este gasto.
        // Puede ser negativo si el monto de los gastos previos ya excedía la proyección original.
        $saldoRealAntesDeEsteGasto = $saldoDisponible;
        return [
            'monto_excedido' => $montoExcedido,
            'saldo_disponible' => $saldoRealAntesDeEsteGasto, // Este es el saldo que había ANTES de este gasto
        ];
    }
}
