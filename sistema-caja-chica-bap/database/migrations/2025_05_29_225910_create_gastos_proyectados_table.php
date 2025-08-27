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

            // Se añade la clave foránea para la clasificación de bienes y servicios.
            $table->foreignId('clasificacion_bien_servicio_id')
                ->nullable() 
                ->constrained('clasificaciones_bien_servicio', 'id_clasificacion_bien_servicio')
                ->onDelete('set null'); 

            // Se añade la clave foránea para el tipo de impuesto.
            $table->foreignId('tipo_impuesto_id')
                ->nullable()
                ->constrained('tipos_impuesto', 'id_tipo_impuesto')
                ->onDelete('set null'); 

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
