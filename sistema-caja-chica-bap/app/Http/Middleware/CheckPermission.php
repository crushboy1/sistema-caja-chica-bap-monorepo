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
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $user = Auth::user();

        if (!$user || !$user->role) {
            return response()->json(['message' => 'Acceso denegado. Usuario no autenticado o sin rol.'], 403);
        }

        // Cargar los permisos del rol una sola vez para eficiencia.
        $user->load('role.permissions');
        $userPermissions = $user->role->permissions->pluck('name');
        $hasPermission = false;
        // Iterar sobre todos los permisos requeridos por la ruta.
        foreach ($permissions as $permissionName) {
            // Si el usuario tiene AL MENOS UNO de los permisos requeridos, se le concede el acceso.
            if ($userPermissions->contains($permissionName)) {
                $hasPermission = true;
                break;
            }
        }

        if (!$hasPermission) {
            return response()->json(['message' => 'No tienes los permisos necesarios para realizar esta acción.'], 403);
        }

        return $next($request);
    }
}
