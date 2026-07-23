<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: fija el "team" de spatie/permission al ISP del usuario.
 *
 * En modo teams, spatie necesita saber qué team (= ISP) está activo para
 * resolver correctamente los roles y permisos. Aquí, en cada petición de un
 * usuario autenticado, le decimos: "el team activo es el isp_id de este
 * usuario". Así $user->hasRole('Ventas') consulta el rol 'Ventas' del ISP
 * correcto y nunca el de otro ISP.
 */
class SetPermissionsTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            app(PermissionRegistrar::class)
                ->setPermissionsTeamId(Auth::user()->isp_id);
        }

        return $next($request);
    }
}
