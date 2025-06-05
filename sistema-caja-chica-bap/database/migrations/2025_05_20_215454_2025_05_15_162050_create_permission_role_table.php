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
        Schema::create('permission_role', function (Blueprint $table) {
            // Clave primaria auto-incremental para la tabla pivote (opcional pero común).
            $table->id();

            // Clave foránea que referencia la tabla 'roles'.
            // 'constrained()' asume que la tabla es 'roles' y la columna es 'id'.
            // on delete cascade: si se elimina un rol, se eliminan todas las asociaciones a ese rol en esta tabla.
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');

            // Clave foránea que referencia la tabla 'permissions'.
            // 'constrained()' asume que la tabla es 'permissions' y la columna es 'id'.
            // on delete cascade: si se elimina un permiso, se eliminan todas las asociaciones a ese permiso en esta tabla.
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');

            // Asegura que la combinación de role_id y permission_id sea única.
            // Un rol no puede tener el mismo permiso asignado dos veces.
            $table->unique(['role_id', 'permission_id']);

            // Timestamps para created_at y updated_at (opcional).
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
