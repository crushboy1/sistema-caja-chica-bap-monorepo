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
        Schema::table('fondo_efectivo', function (Blueprint $table) {
            // Añadimos la nueva columna para el saldo disponible.
            // La colocamos después de 'monto_aprobado' por orden lógico.
            // Es 'nullable' para no causar problemas con registros existentes antes de actualizar sus valores.
            $table->decimal('monto_disponible', 10, 2)->nullable()->after('monto_aprobado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fondo_efectivo', function (Blueprint $table) {
            // Lógica para revertir la migración
            $table->dropColumn('monto_disponible');
        });
    }
};
