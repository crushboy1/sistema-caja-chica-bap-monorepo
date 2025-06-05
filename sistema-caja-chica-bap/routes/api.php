<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\SolicitudFondoController;
use App\Http\Controllers\API\GastoController;
use App\Http\Controllers\API\FondoEfectivoController;
use App\Http\Controllers\API\AreaController; // <-- ¡NUEVA IMPORTACIÓN!

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas de prueba CORS
Route::get('/test-cors', function (Request $request) {
    return response()->json([
        'message' => 'CORS funcionando correctamente',
        'timestamp' => now(),
        'origin' => $request->header('Origin'),
        'method' => $request->method(),
        'headers' => [
            'origin' => $request->header('Origin'),
            'user-agent' => $request->header('User-Agent'),
            'accept' => $request->header('Accept'),
            'content-type' => $request->header('Content-Type')
        ]
    ]);
});

Route::post('/test-cors', function (Request $request) {
    return response()->json([
        'message' => 'POST CORS funcionando correctamente',
        'timestamp' => now(),
        'origin' => $request->header('Origin'),
        'method' => $request->method(),
        'received_data' => $request->all(),
        'headers' => [
            'origin' => $request->header('Origin'),
            'content-type' => $request->header('Content-Type')
        ]
    ]);
});

// Rutas de autenticación (públicas)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register']);
});

// Rutas protegidas por Sanctum (requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    // ✅ Cambiar esta línea para usar el AuthController
    Route::get('/user', [AuthController::class, 'user']);

    // Rutas de autenticación protegidas
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    // Rutas para Solicitud de Fondo
    Route::apiResource('solicitudes-fondo', SolicitudFondoController::class);
    // Rutas para Gestión de Gastos
    Route::apiResource('gastos', GastoController::class);
    Route::apiResource('fondos-efectivo', FondoEfectivoController::class)->parameters([
        'fondos-efectivo' => 'id_fondo'
    ]);
    // ✅ ¡NUEVA RUTA PARA OBTENER ÁREAS!
    Route::get('/areas', [AreaController::class, 'index']);
});

// Ruta para verificar el estado de la API
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now(),
        'app' => config('app.name'),
        'version' => '1.0.0'
    ]);
});
