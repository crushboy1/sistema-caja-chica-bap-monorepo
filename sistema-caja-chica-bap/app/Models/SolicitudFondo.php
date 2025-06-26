<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Carbon\Carbon;

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
        'edit_count',
        'historial_cambios',
    ];

    // Casteo de atributos a tipos nativos de PHP
    protected $casts = [
        'monto_solicitado' => 'decimal:2',
        'historial_cambios' => 'array',
    ];
    protected static function booted(): void
    {
        static::creating(function (SolicitudFondo $solicitud) {
            if (is_null($solicitud->codigo_solicitud)) {
                if (empty($solicitud->id_area)) {
                    // Lanza una excepción si el área no está definida, para prevenir errores.
                    throw new \InvalidArgumentException('El ID del área es requerido para generar el código de solicitud.');
                }
                $solicitud->codigo_solicitud = self::generarCodigoUnico($solicitud->id_area);
            }
        });
    }

    private static function generarCodigoUnico(int $idArea): string
    {
        // Usamos findOrFail para detener la ejecución si el área no existe.
        $area = \App\Models\Area::findOrFail($idArea);
        $acronym = $area->acronym ?: 'XXX';

        $now = Carbon::now();
        $month = $now->format('m'); // '06'
        $year = $now->format('Y');  // '2025'

        // Construimos el prefijo con el formato correcto. Ej: 'SOL-GPS062025-'
        $prefix = "SOL-{$acronym}{$month}{$year}-";

        // Buscamos la última solicitud con el mismo prefijo para obtener el último correlativo.
        $latestRequest = self::where('codigo_solicitud', 'like', $prefix . '%')
            ->orderBy('codigo_solicitud', 'desc')
            ->first();

        $correlative = 1;
        if ($latestRequest) {
            // Extraemos el número del último código. Ej: "SOL-GPS062025-00001" -> "00001"
            $lastCorrelative = (int) substr($latestRequest->codigo_solicitud, strlen($prefix));
            $correlative = $lastCorrelative + 1;
        }

        // Formateamos el correlativo a 5 dígitos con ceros a la izquierda.
        return $prefix . str_pad($correlative, 5, '0', STR_PAD_LEFT);
    }
    /**
     * NUEVO MÉTODO DELEGADO: Gestiona la edición de la solicitud y su trazabilidad.
     *
     * @param array $validatedData Datos validados del formulario de edición.
     * @param \App\Models\User $user El usuario que realiza la edición.
     * @return self
     */
    public function handleEdit(array $validatedData, User $user): self
    {
        return DB::transaction(function () use ($validatedData, $user) {
            // 1. Actualizar los campos principales de la solicitud.
            $this->update($validatedData);

            // 2. Sincronizar los detalles de gastos proyectados.
            $this->detallesGastosProyectados()->delete(); // Se borran los antiguos.
            foreach ($validatedData['gastos_proyectados'] as $gastoData) {
                $this->detallesGastosProyectados()->create([
                    'descripcion_gasto' => $gastoData['descripcion_gasto'],
                    'monto_estimado' => $gastoData['monto_estimado'],
                ]);
            }

            // 3. Incrementar el contador de ediciones.
            $this->increment('edit_count');

            // 4. Registrar la edición en el historial.
            $this->registrarEnHistorial(
                $this->estado, // El estado no cambia
                'Solicitud editada por ' . $user->name . '. (Edición N°' . $this->edit_count . ')',
                $user->id
            );

            return $this;
        });
    }
    public function registrarEnHistorial(string $nuevoEstado, string $observaciones, int $userId): void
    {
        $this->historialEstados()->create([
            'estado_anterior' => $this->estado, // El estado actual antes del cambio
            'estado_nuevo' => $nuevoEstado,
            'observaciones' => $observaciones,
            'id_usuario_accion' => $userId,
            'fecha_cambio' => now(),
        ]);
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
