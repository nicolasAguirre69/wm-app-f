<?php

namespace App\Observers;

use App\Models\Isp;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Observer del modelo Isp.
 *
 * Cuando se crea un ISP, genera automáticamente sus 5 roles por defecto,
 * cada uno con sus permisos, aislados a ese ISP (team_id = isp->id).
 */
class IspObserver
{
    /**
     * Roles por defecto y los permisos que se les asignan.
     * 'Administrador' recibe TODOS los permisos (se resuelve abajo con '*').
     */
    private const ROLES_POR_DEFECTO = [
        'Administrador' => ['*'],
        'Ventas' => ['clientes.ver', 'clientes.crear', 'planes.ver'],
        'Soporte' => ['clientes.ver', 'clientes.editar', 'estados.ver'],
        'Atención al Cliente' => ['clientes.ver', 'clientes.editar'],
        'Facturación' => ['clientes.ver', 'planes.ver'],
    ];

    /**
     * Se ejecuta automáticamente DESPUÉS de crear un ISP.
     */
    public function created(Isp $isp): void
    {
        $registrar = app(PermissionRegistrar::class);

        // Fijamos el team activo al ISP recién creado, para que los roles
        // se creen asociados a ese ISP y no a otro.
        $registrar->setPermissionsTeamId($isp->id);

        // Todos los permisos disponibles (para el rol Administrador).
        $todosLosPermisos = \Spatie\Permission\Models\Permission::pluck('name')->all();

        foreach (self::ROLES_POR_DEFECTO as $nombreRol => $permisos) {
            $rol = Role::firstOrCreate([
                'name' => $nombreRol,
                'guard_name' => 'web',
                'team_id' => $isp->id,
            ]);

            // '*' significa "todos los permisos".
            $permisosAAsignar = $permisos === ['*'] ? $todosLosPermisos : $permisos;

            $rol->syncPermissions($permisosAAsignar);
        }

        $registrar->forgetCachedPermissions();
    }
}
