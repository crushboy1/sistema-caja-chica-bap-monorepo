<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\SolicitudFondoController;
use App\Http\Controllers\API\GastoController;
use App\Http\Controllers\API\FondoEfectivoController;
use App\Http\Controllers\API\AreaController;
use App\Http\Controllers\API\CuentaContableController;
use App\Http\Controllers\API\DocumentoController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí se registran todas las rutas de la API de la aplicación.
|
*/

//==========================================================================
// RUTAS PÚBLICAS
//==========================================================================

// --- Autenticación ---
// Endpoints para que los usuarios inicien sesión y se registren.
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/register', [AuthController::class, 'register']);
});

// --- Estado de la API ---
// Endpoint para verificar que la API está funcionando correctamente.
Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'timestamp' => now()]);
});


//==========================================================================
// RUTAS PROTEGIDAS (Requieren autenticación vía Sanctum)
//==========================================================================
Route::middleware('auth:sanctum')->group(function () {

    // --- Gestión de Usuario y Sesión ---
    Route::get('/user', [AuthController::class, 'user']); // Obtiene la información del usuario autenticado.
    Route::post('/auth/logout', [AuthController::class, 'logout']); // Cierra la sesión del usuario.

    Route::get('/user', function (Request $request) {
        return $request->user()->load('role.permissions', 'area');
    });
    // --- RECURSOS PRINCIPALES (RESTful) ---
    // Proporcionan las operaciones CRUD estándar (index, show, store, update, destroy).

    // Gestión de todas las solicitudes (Apertura, Modificación, etc.).
    Route::apiResource('solicitudes-fondo', SolicitudFondoController::class);
    Route::put('/solicitudes-fondos/{solicitud}/actualizar-estado', [SolicitudFondoController::class, 'actualizarEstado']);
    // Gestión de los fondos de caja chica.
    Route::apiResource('fondos-efectivo', FondoEfectivoController::class)->parameters([
        'fondos-efectivo' => 'id_fondo' // Asegura que el parámetro en la URL sea {id_fondo}
    ]);
    // Endpoints que devuelven listas de datos para selectores, etc.
    //Obtiene lista de cuentas contables.
    Route::get('/cuentas-contables', [CuentaContableController::class, 'index']);
    // Obtiene la lista de todas las áreas.
    Route::get('/areas', [AreaController::class, 'index']);

    // CRUD Básico para Gastos
    Route::apiResource('gastos', GastoController::class)->except(['update']);

    // --- Máquina de Estados para Gastos ---
    // Cada ruta representa una acción clara dentro del flujo de negocio.
    // Paso 2: Aprobación por Jefe de Área
    Route::post('/gastos/{gasto}/approve', [GastoController::class, 'approve'])->name('gastos.approve');
    //Rechazo por Jefe de Área
    Route::post('/gastos/{gasto}/reject-by-jefe', [GastoController::class, 'rejectByJefe'])->name('gastos.reject-by-jefe');
    // Paso 3: Observación por Administración
    Route::post('/gastos/{gasto}/observe', [GastoController::class, 'observe'])->name('gastos.observe');

    // Paso 4 (Parte 1): Jefe devuelve a Colaborador
    Route::post('/gastos/{gasto}/return', [GastoController::class, 'returnToCollaborator'])->name('gastos.return');

    // Paso 4 (Parte 2): Colaborador corrige y reenvía
    // Se usa PUT aquí porque actualiza datos del gasto (monto, glosa, etc.) y la evidencia.
    Route::put('/gastos/{gasto}/resubmit', [GastoController::class, 'resubmit'])->name('gastos.resubmit');

    // Acciones Finales de Administración
    Route::post('/gastos/{gasto}/finalize', [GastoController::class, 'finalizeAsAccounted'])->name('gastos.finalize');
    Route::post('/gastos/{gasto}/reject-final', [GastoController::class, 'rejectFinal'])->name('gastos.reject-final');

    // Generación de Documentos
    Route::post('/documentos/generar-dj', [DocumentoController::class, 'generarDJ']);
    // Obtiene el historial de vida completo de un fondo específico (Apertura, Incrementos, etc.).
    Route::get('/fondos-efectivo/{id_fondo}/historial', [FondoEfectivoController::class, 'getFondoHistory']);
});
