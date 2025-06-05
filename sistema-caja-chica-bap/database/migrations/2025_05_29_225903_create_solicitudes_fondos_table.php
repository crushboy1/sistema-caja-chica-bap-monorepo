<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('solicitudes_fondos', function (Blueprint $table) {
            $table->id(); // ID único de la solicitud (clave primaria interna)

            // Identificador de negocio único y legible para el usuario
            $table->string('codigo_solicitud', 50)->unique()->nullable(); // Ej: 'SOL-2024-001', 'INC-005'. Será generado por la aplicación.

            // Información del solicitante y área
            $table->foreignId('id_solicitante') // ID del usuario que crea la solicitud (Jefe de Área)
                  ->nullable() // Permite que sea NULL si el usuario es eliminado
                  ->constrained('users')
                  ->onDelete('set null'); // Si el usuario se elimina, el campo se pone en NULL

            $table->foreignId('id_area') // ID del área a la que pertenece la solicitud
                  ->constrained('areas')
                  ->onDelete('cascade');

            // *** ELIMINADO: id_responsable_fondo_propuesto (ya que la lógica de firma no está en el alcance actual) ***

            // Detalles de la solicitud
            $table->enum('tipo_solicitud', ['Apertura', 'Incremento', 'Decremento', 'Cierre']); // Tipos de solicitud
            $table->text('motivo_detalle'); // Motivo general de la solicitud o detalle de la modificación/cierre
            $table->decimal('monto_solicitado', 10, 2); // Monto solicitado (para Apertura) o monto de la modificación (para Incremento/Decremento)
            $table->enum('prioridad', ['Baja', 'Media', 'Alta', 'Urgente'])->default('Media'); // Prioridad de la solicitud

            // Estados de la solicitud (según nuestra última definición)
            $table->enum('estado', [
                'Creada',
                'Pendiente Aprobación ADM',
                'Observada ADM',
                'Descargo Enviado ADM',
                'Aprobada ADM', // Estado transicional interno
                'Pendiente Aprobación GRTE',
                'Observada GRTE',
                'Descargo Enviado GRTE',
                'Aprobada', // Estado final de éxito
                'Rechazada Final' // Estado final de rechazo (solo para modificaciones)
            ])->default('Creada'); // Estado inicial al crear la solicitud

            // Campos para motivos de observación/rechazo/descargo
            $table->text('motivo_observacion')->nullable(); // Motivo de observación por ADM o GRTE
            $table->text('motivo_descargo')->nullable(); // Contenido del descargo presentado por el Jefe de Área
            $table->text('motivo_rechazo_final')->nullable(); // Motivo del rechazo definitivo

            // Referencias a los usuarios que participaron en la revisión/aprobación
            $table->foreignId('id_revisor_adm') // Usuario Jefe ADM que revisa/observa/rechaza
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->foreignId('id_aprobador_gerente') // Usuario Gerente que aprueba/observa/rechaza
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            // Relación para solicitudes de modificación o cierre (auto-referencia)
            $table->foreignId('id_solicitud_original') // Para solicitudes de Incremento/Decremento/Cierre, referencia a la solicitud de Apertura original
                  ->nullable() // Es nulo para solicitudes de Apertura
                  ->constrained('solicitudes_fondos')
                  ->onDelete('cascade'); // Si la solicitud original se elimina, las modificaciones/cierres también

            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_fondos');
    }
};
