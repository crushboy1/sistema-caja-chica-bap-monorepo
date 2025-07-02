<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log; // Importar para logging
use Illuminate\Validation\ValidationException; // Para lanzar excepciones de validación si es necesario
use App\Models\SolicitudFondo;

class FondoEfectivo extends Model
{
    use HasFactory;
    protected $table = 'fondo_efectivo';
    protected $primaryKey = 'id_fondo';

    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'codigo_fondo',
        'tipo_fondo',
        'monto_aprobado',
        'monto_disponible',
        'fecha_apertura',
        'estado',
        'fecha_cierre',
        'motivo_cierre',
        'id_solicitud_apertura',
        'id_responsable',
        'id_area',
        'id_proyecto',
    ];

    // Casteo de atributos
    protected $casts = [
        'monto_aprobado' => 'decimal:2',
        'monto_disponible' => 'decimal:2',
        'fecha_apertura' => 'date',
        'fecha_cierre' => 'date',
    ];

    // Se añade la relación con el modelo Proyecto.
    /**
     * Relación opcional: Un fondo puede pertenecer a un proyecto.
     */
    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'id_proyecto', 'id_proyecto');
    }
    /**
     * Relación: Un fondo efectivo fue originado por una solicitud de apertura.
     */
    public function solicitudApertura(): BelongsTo
    {
        return $this->belongsTo(SolicitudFondo::class, 'id_solicitud_apertura');
    }

    /**
     * Relación: Un fondo efectivo tiene un usuario responsable.
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_responsable');
    }

    /**
     * Relación: Un fondo efectivo pertenece a un área.
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'id_area');
    }

    public function gastos()
    {
        // El primer argumento es el modelo relacionado (Gasto).
        // El segundo es la clave foránea en la tabla 'gastos' que apunta a este fondo.
        return $this->hasMany(Gasto::class, 'id_fondo_efectivo', 'id_fondo');
    }
    public function historialReposiciones()
    {
        return $this->hasMany(HistorialReposicion::class, 'id_fondo_efectivo', 'id_fondo');
    }
    /**
     * Genera un código único secuencial para los fondos de efectivo.
     * El formato es 'FNRO-NNNNN'.
     * Este método ha sido movido desde SolicitudFondoController para centralizar
     * la lógica de generación de códigos de FondoEfectivo en su propio dominio.
     *
     * @return string
     */
    public static function generateUniqueFondoCode(): string
    {
        $prefix = 'FNRO';
        // Buscar el último fondo que comience con el prefijo 'FNRO-'
        $latestFondo = self::where('codigo_fondo', 'like', $prefix . '-%')
            ->latest('id_fondo') // Asumiendo que 'id_fondo' es autoincremental
            ->first();

        $nextNumber = 1;
        if ($latestFondo) {
            $lastCode = $latestFondo->codigo_fondo;
            // Asegurar que $lastCode es una cadena no vacía antes de intentar la expresión regular
            if (!empty($lastCode) && is_string($lastCode)) {
                // preg_match devuelve 1 si hay coincidencia, 0 si no hay, y false si hay un error
                if (preg_match('/-(\d+)$/', $lastCode, $matches) === 1) {
                    // Si se encuentra una coincidencia, $matches[1] contendrá los dígitos capturados
                    $nextNumber = (int)$matches[1] + 1;
                }
                // Si no hay coincidencia (0) o error (false), $nextNumber permanece en 1.
            } else {
                Log::warning('El codigo_fondo del último fondo no es una cadena válida o está vacío. ID: ' . $latestFondo->id_fondo . ' Codigo: ' . ($lastCode ?? 'NULL'));
            }
        }

        // Formato final: FNRO-NÚMERO_PADDEADO (ej. FNRO-00001)
        return $prefix . '-' . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Crea un nuevo FondoEfectivo basado en una SolicitudFondo de tipo 'Apertura' aprobada.
     * Este método encapsula la lógica de creación que antes estaba en SolicitudFondoController@manageFondoEfectivo.
     *
     * @param SolicitudFondo $solicitud La solicitud de apertura aprobada.
     * @return FondoEfectivo
     * @throws \Exception Si ocurre un error inesperado al crear el fondo.
     */
    public static function crearDesdeSolicitudApertura(SolicitudFondo $solicitud): FondoEfectivo
    {
        if ($solicitud->tipo_solicitud !== 'Apertura') {
            throw new \InvalidArgumentException("Solo se pueden crear fondos desde solicitudes de tipo 'Apertura'. Tipo proporcionado: " . $solicitud->tipo_solicitud);
        }

        // Buscar si ya existe un fondo para esta solicitud de apertura para evitar duplicados.
        $fondoEfectivo = self::firstOrNew(['id_solicitud_apertura' => $solicitud->id]);

        if ($fondoEfectivo->exists) {
            Log::info('Intento de crear fondo para solicitud de apertura ya existente. Fondo ID: ' . $fondoEfectivo->id_fondo . ' Solicitud ID: ' . $solicitud->id);
            // Si ya existe, podemos actualizarlo o simplemente devolverlo.
            // Para el propósito actual, lo devolvemos, asumiendo que ya fue creado y es válido.
            return $fondoEfectivo;
        }

        // Si no existe, procedemos a crearlo
        $fondoEfectivo->fill([
            'codigo_fondo' => self::generateUniqueFondoCode(), // Genera un código de fondo único
            'tipo_fondo' => $solicitud->tipo_fondo_solicitado,
            'id_responsable' => $solicitud->id_solicitante, // El solicitante de la apertura es el responsable inicial
            'id_area' => $solicitud->id_area,
            'id_proyecto' => $solicitud->id_proyecto,
            'monto_aprobado' => $solicitud->monto_solicitado, // El monto solicitado es el monto aprobado inicial
            'monto_disponible' => $solicitud->monto_solicitado,
            'fecha_apertura' => now()->toDateString(),
            'estado' => 'Activo', // Se crea como activo por defecto
            'id_solicitud_apertura' => $solicitud->id,
        ]);

        try {
            $fondoEfectivo->save();
            Log::info('FondoEfectivo creado exitosamente desde solicitud de apertura.', ['fondo_id' => $fondoEfectivo->id_fondo, 'solicitud_id' => $solicitud->id]);
            return $fondoEfectivo;
        } catch (\Exception $e) {
            Log::error('Error al crear FondoEfectivo desde solicitud de apertura: ' . $e->getMessage(), ['solicitud_id' => $solicitud->id, 'error' => $e->getTraceAsString()]);
            throw new \Exception('Error al crear el fondo efectivo desde la solicitud de apertura.');
        }
    }

    /**
     * Actualiza un FondoEfectivo existente basado en una SolicitudFondo de tipo
     * 'Incremento', 'Decremento' o 'Cierre' aprobada.
     * Este método encapsula la lógica de actualización que antes estaba en SolicitudFondoController@manageFondoEfectivo.
     *
     * @param SolicitudFondo $solicitud La solicitud de modificación aprobada.
     * @return FondoEfectivo El fondo efectivo actualizado.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Si el fondo original no es encontrado.
     * @throws \Exception Si ocurre un error inesperado al actualizar el fondo.
     */
    public static function actualizarDesdeSolicitudModificacion(SolicitudFondo $solicitud): FondoEfectivo
    {
        if (!in_array($solicitud->tipo_solicitud, ['Incremento', 'Decremento', 'Cierre'])) {
            throw new \InvalidArgumentException("Solo se pueden actualizar fondos desde solicitudes de tipo 'Incremento', 'Decremento' o 'Cierre'. Tipo proporcionado: " . $solicitud->tipo_solicitud);
        }

        // Se busca el fondo original asociado a la solicitud de apertura que esta solicitud modifica.
        $fondoOriginal = self::where('id_solicitud_apertura', $solicitud->id_solicitud_original)->firstOrFail();

        try {
            if ($solicitud->tipo_solicitud === 'Incremento' || $solicitud->tipo_solicitud === 'Decremento') {
                // Calcula la diferencia entre el nuevo monto y el antiguo
                $diferencia = $solicitud->monto_solicitado - $fondoOriginal->monto_aprobado;
                // Actualiza el monto aprobado al nuevo valor
                $fondoOriginal->monto_aprobado = $solicitud->monto_solicitado;
                // Ajusta el saldo disponible actual sumando o restando la diferencia
                $fondoOriginal->monto_disponible += $diferencia;
                Log::info('FondoEfectivo actualizado por solicitud de ' . $solicitud->tipo_solicitud, ['fondo_id' => $fondoOriginal->id_fondo, 'nuevo_monto' => $fondoOriginal->monto_aprobado]);
            } elseif ($solicitud->tipo_solicitud === 'Cierre') {
                $fondoOriginal->estado = 'Cerrado';
                $fondoOriginal->fecha_cierre = now()->toDateString();
                $fondoOriginal->motivo_cierre = $solicitud->motivo_detalle; // Usar el motivo de la solicitud de cierre
                $fondoOriginal->monto_aprobado = 0.00; // Cuando un fondo se cierra, su monto aprobado debe ser 0.
                $fondoOriginal->monto_disponible = 0.00;
                Log::info('FondoEfectivo cerrado por solicitud.', ['fondo_id' => $fondoOriginal->id_fondo, 'motivo_cierre' => $fondoOriginal->motivo_cierre]);
            }

            $fondoOriginal->save();
            return $fondoOriginal;
        } catch (\Exception $e) {
            Log::error('Error al actualizar FondoEfectivo desde solicitud de modificación: ' . $e->getMessage(), ['solicitud_id' => $solicitud->id, 'fondo_original_id' => $fondoOriginal->id_fondo ?? 'N/A', 'error' => $e->getTraceAsString()]);
            throw new \Exception('Error al actualizar el fondo de efectivo desde la solicitud de modificación.');
        }
    }
}
