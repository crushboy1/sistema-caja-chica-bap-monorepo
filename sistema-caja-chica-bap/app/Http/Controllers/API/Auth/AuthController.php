<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User; // Asegúrate de importar el modelo User

class AuthController extends Controller
{
    /**
     * Autenticar al usuario y generar un token de acceso.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validar las credenciales de entrada
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Intentar autenticar al usuario usando las credenciales.
        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user(); // Obtener el usuario autenticado

            // Generar un token de texto plano para el usuario.
            // Este token es para autenticación basada en API (ej. SPA sin cookies)
            // Si solo usas cookies/sesiones, este token no es estrictamente necesario para el frontend.
            $token = $user->createToken('auth_token')->plainTextToken;
            
            // Cargar la relación 'role' y sus 'permissions' para la respuesta del login
            $user->load('role.permissions'); 
            // Log para comparar
            \Log::info('LOGIN - User con relaciones: ' . json_encode($user->toArray()));
            // Retornar una respuesta JSON con un mensaje de éxito, los datos del usuario y el token.
            return response()->json([
                'message' => 'Login exitoso',
                'user' => $user, // Esto ya está correcto y devuelve el usuario con permisos
                'token' => $token,
            ]);
        }

        // Si la autenticación falla, lanzar una excepción de validación con un mensaje de error.
        throw ValidationException::withMessages([
            'email' => [trans('auth.failed')],
        ]);
    }

    /**
     * Cierra la sesión del usuario autenticado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Si el usuario está autenticado y tiene un token personal (para API token auth)
        // entonces revoca ese token. Esto es para casos de API token, no para sesiones web.
        // El error 'TransientToken' ocurre cuando no hay un token de API persistente.
        // Lo eliminamos para evitar el error si solo usas autenticación basada en sesión/cookies.
        // if ($request->user() && $request->user()->currentAccessToken()) {
        //     $request->user()->currentAccessToken()->delete();
        // }
        
        // Simplemente cerramos la sesión web, que es lo que se usa para el frontend con cookies.
        Auth::guard('web')->logout(); // Invalida la sesión del guard 'web'

        $request->session()->invalidate(); // Invalida la sesión actual
        $request->session()->regenerateToken(); // Regenera el token CSRF para futuras solicitudes, por seguridad

        return response()->json(['message' => 'Logout exitoso']);
    }

    /**
     * Obtener el usuario autenticado.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
   public function user(Request $request) 
{
    $user = $request->user();
    
    // Cargar tanto 'area' como 'role.permissions'
    $user->load(['area', 'role.permissions']);
    
    return response()->json([
        'user' => $user,
        'authenticated' => true,
        'timestamp' => now()
    ]);
}

    /**
     * Obtener el token CSRF para SPA.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function csrfToken()
    {
        return response()->json([
            'csrf_token' => csrf_token()
        ]);
    }
}
