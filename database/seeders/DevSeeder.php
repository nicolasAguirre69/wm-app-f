<?php

namespace Database\Seeders;

use App\Models\Isp;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * Datos SOLO para desarrollo: un usuario normal de prueba (no Super Admin)
 * con rol Administrador del ISP Principal, para probar la app con permisos
 * reales. No se ejecuta en producción.
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        // Seguridad: solo en entorno local.
        if (! app()->environment('local')) {
            return;
        }

        $ispPrincipal = Isp::where('tipo', 'principal')->first();

        if (! $ispPrincipal) {
            return;
        }

        $user = User::firstOrCreate(
            ['email' => 'op@wmc.test'],
            [
                'name' => 'Op Principal',
                'password' => Hash::make('password'),
                'isp_id' => $ispPrincipal->id,
                'is_super_admin' => false,
                'activo' => true,
                'email_verified_at' => now(),
            ],
        );

        // Fijamos el team (ISP) para asignar el rol correcto y le damos
        // el rol Administrador de su ISP.
        app(PermissionRegistrar::class)->setPermissionsTeamId($ispPrincipal->id);
        $user->assignRole('Administrador');
    }
}
