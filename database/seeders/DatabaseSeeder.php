<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Orquesta todos los seeders del sistema en orden. Por ahora solo el
     * SuperAdminSeeder (ISP Principal + Super Admin). Aquí iremos agregando
     * los seeders de catálogos globales (TipoPlan, TipoServicio) más adelante.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,   // 1º: permisos globales
            SuperAdminSeeder::class,   // 2º: ISP Principal (dispara IspObserver) + Super Admin
        ]);
    }
}
