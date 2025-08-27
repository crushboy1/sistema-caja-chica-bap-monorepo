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
        Schema::create('tipos_impuesto', function (Blueprint $table) {
            $table->id('id_tipo_impuesto');
            $table->string('nombre', 50)->unique();
            $table->decimal('porcentaje', 5, 2); 
            $table->decimal('factor_calculo', 8, 2);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_impuesto');
    }
};
