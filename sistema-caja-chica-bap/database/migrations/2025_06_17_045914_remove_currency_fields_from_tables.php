<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ejecuta las migraciones.
     *

     * Su único objetivo es limpiar la tabla 'gastos' eliminando los campos de cálculo
     * de moneda que ya no son necesarios, pero CONSERVANDO las columnas 'moneda' y 'glosa'.
     */
    public function up(): void
    {
        Schema::table('gastos', function (Blueprint $table) {

            // las columnas relacionadas con el cálculo de conversión de moneda.
            $table->dropColumn([
                'tipo_cambio_referencial',  
                'tipo_cambio',              
                'monto_final_pen',          
            ]);
        });

        
    }

    /**
     * Revierte las migraciones.
     *
     * Este método 'down' restaura la tabla 'gastos' a su estado anterior,
     * volviendo a añadir las columnas de cálculo eliminadas.
     */
    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            // Se restauran las columnas en el orden que tenían originalmente para una reversión segura.
            $table->decimal('tipo_cambio_referencial', 8, 4)->nullable()->after('moneda');
            $table->decimal('tipo_cambio', 8, 4)->nullable()->after('tipo_cambio_referencial');
            $table->decimal('monto_final_pen', 10, 2)->nullable()->after('tipo_cambio');
        });
    }
};
