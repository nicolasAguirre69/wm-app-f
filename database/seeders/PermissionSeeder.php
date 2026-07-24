<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Catálogo base de permisos del sistema.
     *
     * Los permisos son GLOBALES (iguales para todos los ISPs). Lo que cambia
     * por ISP son los roles que los agrupan (eso lo hace el IspObserver).
     */
    public function run(): void
    {
        // En modo teams, fijamos team_id = null para crear permisos globales
        // (no atados a ningún ISP en particular).
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $permissions = [
            // Clientes
            'clientes.ver', 'clientes.crear', 'clientes.editar', 'clientes.eliminar',
            // Usuarios
            'usuarios.ver', 'usuarios.crear', 'usuarios.editar', 'usuarios.eliminar',
            // Planes
            'planes.ver', 'planes.crear', 'planes.editar', 'planes.eliminar',
            // Barrios
            'barrios.ver', 'barrios.crear', 'barrios.editar', 'barrios.eliminar',
            // Redes
            'redes.ver', 'redes.crear', 'redes.editar', 'redes.eliminar',
            // Ciudades
            'ciudades.ver', 'ciudades.crear', 'ciudades.editar', 'ciudades.eliminar',
            // Estados de cliente
            'estados.ver', 'estados.crear', 'estados.editar', 'estados.eliminar',
            // Dashboard
            'dashboard.ver',
            // Reportes
            'reportes.exportar',
        ];

        foreach ($permissions as $permission) {
            // firstOrCreate: idempotente, no duplica si ya existe.
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        // Limpiamos la caché de permisos de spatie para que los nuevos
        // se reconozcan de inmediato.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
