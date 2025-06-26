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
        Schema::create('areas', function (Blueprint $table) {
                // Clave primaria auto-incremental.
                $table->id();

                // Nombre del área (ej: 'Administración', 'Contabilidad', 'TI').
                $table->string('name')->unique();
                //generar códigos de solicitud únicos y legibles.
                $table->string('acronym', 10)->nullable();
                // Descripción opcional del área.
                $table->string('description')->nullable();
                // Timestamps para created_at y updated_at.
                $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
