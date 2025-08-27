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
    Schema::create('tipos_documento_comprobante', function (Blueprint $table) {
        $table->id();
        $table->string('nombre', 100); 
        $table->string('codigo_comprobante', 5)->unique()->nullable();
        $table->boolean('activo')->default(true); 
        $table->timestamps(); 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_documento_comprobante');
    }
};
