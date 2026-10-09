<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Laravel Fortify viene instalado pero la app no lo usa: el login, el
        // perfil y la contraseña tienen rutas propias (routes/auth.php y
        // routes/settings.php). Sin esto Fortify registra rutas con los mismos
        // nombres (ej. password.update), "php artisan optimize" falla, y además
        // quedarían expuestas rutas que no queremos (registro, recuperar clave).
        if (class_exists(\Laravel\Fortify\Fortify::class)) {
            \Laravel\Fortify\Fortify::ignoreRoutes();
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // En producción, toda URL generada usa https:// (evita contenido mixto).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Bypass del Super Admin: se ejecuta ANTES de cualquier Policy o
        // verificación de permiso. Si el usuario es Super Admin, autoriza
        // todo de inmediato (devuelve true). Para los demás, devuelve null
        // para que la autorización siga su curso normal (permisos/Policies).
        Gate::before(function (User $user, string $ability) {
            return $user->is_super_admin ? true : null;
        });
    }
}
