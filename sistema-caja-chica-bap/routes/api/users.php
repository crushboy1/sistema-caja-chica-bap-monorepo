<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\UserController;

// Se asume que este archivo se carga dentro de un grupo con el middleware 'auth:sanctum'.
// La autorización por rol se maneja dentro del controlador.

Route::apiResource('users', UserController::class);
Route::post('users/{user}/activate', [UserController::class, 'activate']);
