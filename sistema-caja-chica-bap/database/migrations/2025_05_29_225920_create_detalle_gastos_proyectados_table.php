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
        Schema::create('detalle_gastos_proyectados', function (Blueprint $table) {
            $table->id(); // ID único del detalle de gasto

            $table->foreignId('id_solicitud_fondo') // ID de la solicitud a la que pertenece este gasto proyectado
                  ->constrained('solicitudes_fondos')
                  ->onDelete('cascade'); // Si la solicitud se elimina, sus gastos proyectados también

            $table->string('descripcion_gasto'); // Descripción del tipo de gasto (ej. "Materiales de oficina", "Viáticos")
            // Eliminadas: 'cantidad' y 'precio_unidad' según la última definición.
            $table->decimal('monto_estimado', 10, 2); // Monto estimado para este gasto (corresponde a monto_mensual_estimado del frontend)

            $table->timestamps(); // created_at y updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalle_gastos_proyectados');
    }
};
