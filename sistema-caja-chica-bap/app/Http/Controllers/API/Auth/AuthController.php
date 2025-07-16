<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;

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
        // 1. Validar las credenciales de entrada
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // 2. Intentar autenticar al usuario
        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();

            // Verificación de estado del usuario
            // Si el usuario no está activo, se cierra la sesión y se devuelve un error.
            if (!$user->activo) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Lanza una excepción de validación para devolver un error 422 claro al frontend.
                throw ValidationException::withMessages([
                    'email' => ['Su cuenta ha sido desactivada. Por favor, contacte al administrador.'],
                ]);
            }

            // 4. Si el usuario está activo, se procede con el login normal.
            $token = $user->createToken('auth_token')->plainTextToken;
            $user->load('role.permissions');

            return response()->json([
                'message' => 'Login exitoso',
                'user' => $user,
                'token' => $token,
            ]);
        }

        // 5. Si la autenticación falla, lanzar una excepción.
        throw ValidationException::withMessages([
            'email' => [trans('auth.failed')],
        ]);
    }

    /**
     * Cierra la sesión del usuario autenticado.
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout exitoso']);
    }

    /**
     * Obtener el usuario autenticado.
     */
    public function user(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(null, 401);
        }
        $user->load(['area', 'role.permissions']);

        return response()->json([
            'user' => $user,
            'authenticated' => true,
            'timestamp' => now()
        ]);
    }

    /**
     * Obtener el token CSRF para SPA.
     */
    public function csrfToken()
    {
        return response()->json([
            'csrf_token' => csrf_token()
        ]);
    }
}
