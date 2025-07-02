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
        // Esta es una tabla pivote que conecta una Solicitud con múltiples Gastos Proyectados,
        // y almacena el monto estimado para cada uno.
        Schema::create('solicitud_gasto_proyectado', function (Blueprint $table) {
            $table->id();

            $table->foreignId('solicitud_fondo_id')->constrained('solicitudes_fondos')->onDelete('cascade');
            $table->foreignId('gasto_proyectado_id')->constrained('gastos_proyectados', 'id_gasto_proyectado')->onDelete('cascade');

            // Columna extra en la tabla pivote para almacenar el monto.
            $table->decimal('monto_estimado', 10, 2);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitud_gasto_proyectado');
    }
};
