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
        Schema::create('excepciones_cierre', function (Blueprint $table) {
            $table->id();

            // Vincula la excepción a un período específico en la tabla de cierres.
            $table->foreignId('id_cierre_mensual')->constrained('cierres_mensuales')->onDelete('cascade');

            // El usuario que recibe el permiso para registrar fuera de fecha.
            $table->foreignId('id_usuario_excepcion')->constrained('users')->onDelete('cascade');

            // Trazabilidad: El administrador que otorgó el permiso.
            $table->foreignId('id_usuario_otorga')->constrained('users')->onDelete('cascade');

            // Define hasta qué fecha es válida la excepción.
            $table->date('fecha_expiracion');

            // Justificación de por qué se otorgó el permiso.
            $table->text('motivo');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('excepciones_cierre');
    }
};
