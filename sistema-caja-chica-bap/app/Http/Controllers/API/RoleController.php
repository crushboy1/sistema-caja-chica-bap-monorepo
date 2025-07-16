<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Role; // Asegúrate de importar el modelo Role
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    /**
     * Muestra una lista de todos los roles disponibles en el sistema.
     * Protegido para que solo los administradores puedan acceder a esta lista.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Se añade una capa de seguridad para asegurar que solo los administradores
        // puedan obtener la lista completa de roles.
        if (!Auth::user()->hasAnyRole(['super_admin'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        // Devuelve todos los roles, ordenados por nombre.
        $roles = Role::orderBy('display_name')->get();

        return response()->json(['roles' => $roles]);
    }
}
