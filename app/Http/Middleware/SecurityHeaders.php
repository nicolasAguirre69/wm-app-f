<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Añade cabeceras de seguridad a todas las respuestas. Son defensas estándar
 * contra clickjacking, sniffing de MIME y fuga de información de referencia.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // El navegador no "adivina" tipos MIME (mitiga algunos XSS).
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // La app no puede cargarse dentro de un <iframe> ajeno (anti-clickjacking).
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Cuánta información de origen se envía al navegar hacia otros sitios.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Desactiva APIs del navegador que la app no usa.
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // HSTS: obliga a usar HTTPS durante un año. Solo si la petición ya es segura.
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
