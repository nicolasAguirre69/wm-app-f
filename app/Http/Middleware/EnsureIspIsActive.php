<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: garantiza que el ISP del usuario esté activo.
 *
 * Si el Super Admin desactiva un ISP, todo su personal pierde acceso de
 * inmediato: se cierra su sesión y se le redirige al login con un mensaje.
 *
 * El Super Admin queda EXENTO (su acceso es global, no depende de un ISP).
 */
class EnsureIspIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        // Solo evaluamos usuarios autenticados que NO sean Super Admin.
        if ($user && ! $user->is_super_admin) {
            // Si no tiene ISP, o su ISP existe pero está inactivo → fuera.
            if (! $user->isp || ! $user->isp->activo) {
                // Cerramos la sesión por completo.
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Redirigimos al login con un mensaje explicativo.
                return redirect()->route('login')->withErrors([
                    'email' => 'Tu ISP ha sido desactivado. Contacta al administrador.',
                ]);
            }
        }

        return $next($request);
    }
}
