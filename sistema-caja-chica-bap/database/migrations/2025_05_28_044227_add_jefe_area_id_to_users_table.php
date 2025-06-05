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
        Schema::table('users', function (Blueprint $table) {
            // Añade la columna jefe_area_id
            // Debe ser unsignedBigInteger porque referencia a un 'id' (bigint unsigned)
            // Puede ser nullable si un usuario no tiene un jefe de área asignado (ej. Gerente General, o un Jefe de Área sin supervisor directo)
            $table->unsignedBigInteger('jefe_area_id')->nullable()->after('area_id'); // Colócala después de 'area_id' para un orden lógico

            // Define la clave foránea
            // Referencia la columna 'id' de la misma tabla 'users'
            // onDelete('set null') es apropiado: si un jefe de área es eliminado, sus colaboradores no se borran,
            // sino que su jefe_area_id se establece en null, manteniendo la integridad.
            $table->foreign('jefe_area_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Primero, elimina la clave foránea para evitar errores
            $table->dropForeign(['jefe_area_id']);

            // Luego, elimina la columna
            $table->dropColumn('jefe_area_id');
        });
    }
};
