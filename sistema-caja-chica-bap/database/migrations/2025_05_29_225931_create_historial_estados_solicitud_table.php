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
        Schema::create('historial_estados_solicitud', function (Blueprint $table) {
            $table->id(); // ID único del registro de historial

            $table->foreignId('id_solicitud_fondo') // ID de la solicitud a la que pertenece este historial
                  ->constrained('solicitudes_fondos')
                  ->onDelete('cascade'); // Si la solicitud se elimina, su historial también

            $table->string('estado_anterior')->nullable(); // Estado de la solicitud antes del cambio (puede ser nulo para el estado inicial)
            $table->string('estado_nuevo'); // Nuevo estado de la solicitud

            $table->text('observaciones')->nullable(); // Comentarios o motivos asociados al cambio de estado (ej. motivo de observación, rechazo, descargo)

            $table->foreignId('id_usuario_accion') // ID del usuario que realizó la acción que causó el cambio de estado
                  ->nullable() // Puede ser nulo si el cambio es automático del sistema (ej. de Creada a Pendiente ADM)
                  ->constrained('users')
                  ->onDelete('set null');

            $table->timestamp('fecha_cambio')->useCurrent(); // Fecha y hora del cambio de estado

            $table->timestamps(); // created_at y updated_at (estos son para el registro del historial en sí)
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_estados_solicitud');
    }
};
