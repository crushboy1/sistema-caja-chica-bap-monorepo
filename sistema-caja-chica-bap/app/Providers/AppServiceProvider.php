<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Esta sección fuerza a Laravel a usar siempre la URL base
        // definida en tu archivo .env (APP_URL) al generar cualquier URL,
        // incluyendo los enlaces de paginación.
        // Esto es crucial en entornos Docker para que las URLs sean correctas
        // para el frontend que se ejecuta en 'localhost'.
        // Se aplica solo en el entorno local para no afectar a producción.
        URL::forceRootUrl(config('app.url'));
    }
}
