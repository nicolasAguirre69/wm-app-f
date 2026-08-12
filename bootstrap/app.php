<?php

use App\Http\Middleware\EnsureIspIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPermissionsTeam;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Confía en el proxy de Plesk (nginx/Apache) para leer el protocolo real
        // (X-Forwarded-Proto). Sin esto, Laravel cree que la conexión es HTTP
        // aunque el usuario esté en HTTPS, y no marca las cookies como seguras.
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO);

        $middleware->web(append: [
            SetPermissionsTeam::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SecurityHeaders::class,
        ]);

        // Alias para usar el middleware por nombre en las rutas.
        $middleware->alias([
            'isp.active' => EnsureIspIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
