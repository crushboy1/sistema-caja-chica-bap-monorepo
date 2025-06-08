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
        Schema::table('gastos', function (Blueprint $table) {
            // Se añade un campo booleano para el proyecto.
            $table->boolean('pertenece_proyecto')->default(false)->after('glosa');
            // Se añade un campo de texto para comentarios adicionales.
            $table->text('comentario')->nullable()->after('pertenece_proyecto');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropColumn(['pertenece_proyecto', 'comentario']);
        });
    }
};
