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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            // Nombre único del permiso (para uso interno/código). Corresponde a 'nombre_permiso'.
            // Ej: 'create_solicitud', 'validar_gasto_ja', 'generate_f07'.
            $table->string('name')->unique();
            // Nombre legible para mostrar
            $table->string('display_name')->nullable();
            // Descripción opcional del permiso
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
