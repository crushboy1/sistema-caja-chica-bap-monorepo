<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;

// Se asume que este archivo se carga dentro de un grupo con el middleware 'auth:sanctum'.

// COMENTARIO BAP: Se define la ruta GET para el listado principal ('index') de forma separada
// para poder aplicarle su propio middleware de permisos de forma explícita.
Route::get('/users', [UserController::class, 'index'])
    ->name('users.index')
    ->middleware('check.permission:admin.users.manage');

Route::get('/users/list-for-select', [UserController::class, 'listForSelect'])
    ->name('users.listForSelect')
    ->middleware('check.permission:admin.excepciones.manage');

// Se define el apiResource pero se le indica que ignore el método 'index',
// ya que lo hemos definido manualmente arriba para asignarle su permiso específico.
// El resto de las rutas (store, show, update, destroy) se crearán automáticamente.
Route::apiResource('users', UserController::class)->except(['index']);

Route::post('users/{user}/activate', [UserController::class, 'activate'])
    ->name('users.activate')
    ->middleware('check.permission:admin.users.manage'); 
Route::get('/users/list-for-select', [UserController::class, 'listForSelect'])
    ->name('users.listForSelect');