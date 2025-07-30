<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permissionName  El nombre del permiso requerido para acceder a la ruta.
     */
    public function handle(Request $request, Closure $next, string $permissionName): Response
    {
        $user = Auth::user();

        // Si no hay usuario o el usuario no tiene un rol asignado, denegar acceso.
        if (!$user || !$user->role) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        // Cargar los permisos asociados al rol del usuario para hacer la verificación.
        // Se asume que en tu modelo User tienes una relación llamada 'role'
        // y en el modelo Role tienes una relación llamada 'permissions'.
        $user->load('role.permissions');

        // Verificar si la colección de permisos del rol contiene el permiso requerido.
        $hasPermission = $user->role->permissions->contains('name', $permissionName);

        if (!$hasPermission) {
            return response()->json(['message' => 'No tienes los permisos necesarios para realizar esta acción.'], 403);
        }

        // Si tiene el permiso, continuar con la solicitud.
        return $next($request);
    }
}
