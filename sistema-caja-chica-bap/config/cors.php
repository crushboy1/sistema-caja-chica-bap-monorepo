<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Configuración específica para tu setup Docker
    'allowed_origins' => [
        'http://localhost:3000',          // Frontend Vue
        'http://127.0.0.1:3000',          // Frontend Vue alternativo
        'http://caja-chica-frontend:3000', // Comunicación entre contenedores
        'http://localhost:8080',          // Backend Laravel (para testing)
        'http://127.0.0.1:8080'           // Backend Laravel alternativo
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'Origin',
        'Cache-Control'
    ],

    'exposed_headers' => [
        'Cache-Control',
        'Content-Language',
        'Content-Type',
        'Expires',
        'Last-Modified',
        'Pragma'
    ],

    'max_age' => 0,

    // Importante: true para usar cookies/sesiones con Sanctum
    'supports_credentials' => true,

];