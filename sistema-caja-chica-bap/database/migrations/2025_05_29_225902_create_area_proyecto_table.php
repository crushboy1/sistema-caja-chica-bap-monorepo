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
        Schema::create('area_proyecto', function (Blueprint $table) {
             $table->id();
            // Se define la columna de la clave foránea.
            $table->unsignedBigInteger('id_area');
            $table->unsignedBigInteger('id_proyecto');

            // Se define explícitamente la relación:
            $table->foreign('id_area')->references('id')->on('areas')->onDelete('cascade');
            $table->foreign('id_proyecto')->references('id_proyecto')->on('proyectos')->onDelete('cascade');
            $table->unique(['id_area', 'id_proyecto']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('area_proyecto');
    }
};
