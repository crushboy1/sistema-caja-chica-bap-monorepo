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
        Schema::create('activity_logs', function (Blueprint $table) {
            // COMENTARIO BAP: ID único para cada registro de log.
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            //índice para optimizar las búsquedas por tipo de acción.
            $table->string('action_type')->index();
            // Estos dos campos ('subject') crean una relación polimórfica.
            // Nos permiten asociar este log con CUALQUIER otro modelo (Proyecto, CuentaContable, etc.)
            // sin necesidad de crear una tabla de log para cada uno.
            $table->string('subject_type'); // Guarda el nombre del modelo (ej. 'App\Models\Proyecto').
            $table->unsignedBigInteger('subject_id'); // Guarda el ID del registro afectado (ej. 5).
            $table->json('properties')->nullable();
            $table->timestamps();
            // índice compuesto en los campos 'subject' para que
            // buscar todos los logs de un registro específico (ej. todos los cambios del Proyecto con ID 5)
            // sea extremadamente rápido.
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
