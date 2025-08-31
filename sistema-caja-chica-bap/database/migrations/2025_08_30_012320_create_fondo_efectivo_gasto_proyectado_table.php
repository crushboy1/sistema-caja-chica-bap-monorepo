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
        Schema::create('fondo_efectivo_gasto_proyectado', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fondo_efectivo_id')->constrained('fondo_efectivo', 'id_fondo')->onDelete('cascade');
            $table->foreignId('gasto_proyectado_id')->constrained('gastos_proyectados', 'id_gasto_proyectado')->onDelete('cascade');
            $table->decimal('monto_estimado', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fondo_efectivo_gasto_proyectado');
    }
};
