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
        // Esta tabla contendrá el catálogo de "Gastos Proyectados" que el admin puede gestionar.
        Schema::create('gastos_proyectados', function (Blueprint $table) {
            $table->id('id_gasto_proyectado');
            $table->string('descripcion')->unique();
            $table->boolean('activo')->default(true);
            // Relación: Cada Gasto Proyectado pertenece a UNA cuenta contable.
            $table->foreignId('id_cuenta_contable')
                ->constrained('cuentas_contables', 'id')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gastos_proyectados');
    }
};
