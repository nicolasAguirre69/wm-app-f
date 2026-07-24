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
            PermissionSeeder::class,     // 1º: permisos globales
            TipoPlanSeeder::class,       // 2º: catálogo global tipos de plan
            TipoServicioSeeder::class,   // 3º: catálogo global tipos de servicio
            SuperAdminSeeder::class,     // 4º: ISP Principal (dispara IspObserver) + Super Admin
            CiudadSeeder::class,         // 5º: ciudades iniciales del ISP Principal
            BarrioSeeder::class,         // 6º: barrios (el BarrioObserver crea sus 16 redes solo)
            DevSeeder::class,            // 7º: usuario de pruebas (solo en local)
        ]);
    }
}
