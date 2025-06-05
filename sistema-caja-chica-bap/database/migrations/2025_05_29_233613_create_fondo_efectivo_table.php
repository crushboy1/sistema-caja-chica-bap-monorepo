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
        Schema::create('fondo_efectivo', function (Blueprint $table) {
            $table->id('id_fondo'); // PK: id_fondo
            $table->string('codigo_fondo', 50)->unique()->nullable();
            $table->decimal('monto_aprobado', 10, 2); // Monto actual del fondo aprobado
            $table->date('fecha_apertura'); // Fecha en que el fondo fue activado
            $table->enum('estado', ['Activo', 'Cerrado'])->default('Activo'); // Estado del fondo (no de la solicitud)
            $table->date('fecha_cierre')->nullable(); // Fecha de cierre del fondo, si aplica
            $table->text('motivo_cierre')->nullable(); // Motivo del cierre del fondo, si aplica

            $table->foreignId('id_solicitud_apertura') // FK: Referencia a la solicitud de Apertura que originó este fondo
                  ->constrained('solicitudes_fondos')
                  ->onDelete('cascade'); // Si la solicitud de apertura se elimina, el fondo también

            $table->foreignId('id_responsable') // FK: Usuario responsable actual del fondo (Jefe de Área)
                  ->constrained('users')
                  ->onDelete('restrict'); // No permitir eliminar usuario si es responsable de un fondo activo

            $table->foreignId('id_area') // FK: Área a la que pertenece este fondo
                  ->constrained('areas')
                  ->onDelete('cascade');

            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fondo_efectivo');
    }
};
