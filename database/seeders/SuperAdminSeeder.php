<?php

namespace Database\Seeders;

use App\Enums\TipoIsp;
use App\Models\Isp;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Crea el ISP Principal y el usuario Super Administrador.
     *
     * Usa firstOrCreate para ser IDEMPOTENTE: si ya existen, no los
     * duplica. Así puedes correr `php artisan db:seed` varias veces sin
     * ensuciar la base de datos.
     */
    public function run(): void
    {
        // 1. ISP Principal (busca uno de tipo 'principal'; si no hay, lo crea).
        $ispPrincipal = Isp::firstOrCreate(
            ['tipo' => TipoIsp::Principal->value],
            ['nombre' => 'ISP Principal', 'activo' => true],
        );

        // 2. Usuario Super Admin, asociado al ISP Principal.
        User::firstOrCreate(
            ['email' => 'admin@wmc.test'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('password'),
                'isp_id' => $ispPrincipal->id,
                'is_super_admin' => true,
                'activo' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
