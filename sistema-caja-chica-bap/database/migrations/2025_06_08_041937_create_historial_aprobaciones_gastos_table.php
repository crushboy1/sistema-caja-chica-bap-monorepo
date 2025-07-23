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
        Schema::create('historial_aprobaciones_gastos', function (Blueprint $table) {
            $table->id();
            // Se relaciona con la nueva tabla 'gastos'.
            $table->foreignId('id_gasto')->constrained('gastos')->onDelete('cascade'); // Si se borra un gasto, se borra su historial.
            
            $table->string('estado_anterior');
            $table->string('estado_nuevo');
            
            // Se relaciona con la tabla 'users' para saber quién realizó la acción.
            $table->foreignId('id_usuario_accion')->constrained('users', 'id');

            // Para guardar el motivo del rechazo, la instrucción del jefe, etc.
            $table->text('comentario')->nullable();
            $table->json('cambios_realizados')->nullable();
            $table->timestamp('fecha_cambio');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_aprobaciones_gastos');
    }
};
