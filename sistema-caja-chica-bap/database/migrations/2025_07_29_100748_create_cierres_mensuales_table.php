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
        Schema::create('cierres_mensuales', function (Blueprint $table) {
            $table->id();

            // Almacena el primer día del mes para identificar el período.
            // Ej: '2025-07-01' para representar Julio de 2025.
            // Se añade un índice único para asegurar que solo haya un registro por período.
            $table->date('periodo')->unique();

            // Estado del período contable. Por defecto, todos los meses nacen 'Abierto'.
            $table->enum('estado', ['Abierto', 'Cerrado'])->default('Abierto');

            // Trazabilidad: Quién realizó la última acción sobre este cierre.
            $table->foreignId('id_usuario_accion')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cierres_mensuales');
    }
};
