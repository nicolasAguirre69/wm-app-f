<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Bypass del Super Admin: se ejecuta ANTES de cualquier Policy o
        // verificación de permiso. Si el usuario es Super Admin, autoriza
        // todo de inmediato (devuelve true). Para los demás, devuelve null
        // para que la autorización siga su curso normal (permisos/Policies).
        Gate::before(function (User $user, string $ability) {
            return $user->is_super_admin ? true : null;
        });
    }
}
