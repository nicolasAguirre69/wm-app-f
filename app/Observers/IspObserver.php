<?php

namespace App\Observers;

use App\Models\EstadoCliente;
use App\Models\Isp;
use App\Models\TipoFalla;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Observer del modelo Isp.
 *
 * Cuando se crea un ISP, genera automáticamente sus 5 roles por defecto,
 * el set estándar de estados de cliente (con colores universales) y, si es
 * una ISP cliente, su plan de TV por defecto.
 */
class IspObserver
{
    /**
     * Roles por defecto y los permisos que se les asignan.
     * 'Administrador' recibe TODOS los permisos (se resuelve abajo con '*').
     */
    private const ROLES_POR_DEFECTO = [
        'Administrador' => ['*'],
        'Ventas' => ['clientes.ver', 'clientes.crear', 'planes.ver', 'tickets.ver'],
        'Soporte' => ['clientes.ver', 'clientes.editar', 'estados.ver',
            'tickets.ver', 'tickets.crear', 'tickets.comentar', 'tickets.editar', 'tickets.cerrar'],
        'Atención al Cliente' => ['clientes.ver', 'clientes.editar', 'tickets.ver', 'tickets.crear', 'tickets.comentar',
            'pagos.ver', 'pagos.registrar'],
        'Facturación' => ['clientes.ver', 'planes.ver', 'tickets.ver', 'pagos.ver', 'pagos.registrar', 'pagos.anular'],
    ];

    /**
     * Estados de cliente estándar (nombre => color universal de la paleta).
     * La ISP principal recibe todos; una ISP cliente solo los permitidos
     * (EstadoCliente::PERMITIDOS_ISP_CLIENTE: Activo y Retirado).
     */
    private const ESTADOS_POR_DEFECTO = [
        'Activo' => '#22c55e',
        'Suspendido' => '#3b82f6',
        'Retirado' => '#ef4444',
        'Corte' => '#f59e0b',
    ];

    /**
     * Se ejecuta automáticamente DESPUÉS de crear un ISP.
     */
    public function created(Isp $isp): void
    {
        self::aplicarDefaults($isp);
    }

    /**
     * Al pasar a la categoría "Gestión completa" la ISP gana el estado Corte y
     * los tipos de falla de tickets. Los roles NO se tocan (podrían estar
     * personalizados).
     */
    public function updated(Isp $isp): void
    {
        if ($isp->wasChanged('categoria')) {
            self::crearCatalogos($isp);
        }
    }

    /**
     * Crea/actualiza los estados y roles por defecto de un ISP. Es idempotente
     * (usa firstOrCreate y syncPermissions), así que sirve tanto al crear el
     * ISP como para RE-sincronizar ISPs existentes tras agregar permisos nuevos.
     */
    public static function aplicarDefaults(Isp $isp): void
    {
        self::crearCatalogos($isp);
        self::sincronizarRoles($isp);
    }

    /**
     * Catálogos por defecto según la categoría de la ISP: estados de cliente,
     * tipos de falla (si tiene tickets) y plan de TV (ISP cliente).
     */
    public static function crearCatalogos(Isp $isp): void
    {
        // Estados estándar del ISP.
        $permitidos = $isp->estadosPermitidos();

        foreach (self::ESTADOS_POR_DEFECTO as $nombre => $color) {
            if ($permitidos !== null && ! in_array($nombre, $permitidos, true)) {
                continue;
            }

            EstadoCliente::firstOrCreate(
                ['isp_id' => $isp->id, 'nombre' => $nombre],
                ['color' => $color],
            );
        }

        // Módulo de tickets (principal y "Gestión completa"): tipos de falla iniciales.
        if ($isp->tieneTickets()) {
            foreach (TipoFalla::POR_DEFECTO as $nombre) {
                TipoFalla::withoutGlobalScopes()->firstOrCreate(['isp_id' => $isp->id, 'nombre' => $nombre]);
            }
        }

        // ISP cliente: su plan de TV por defecto (se asigna solo a cada cliente).
        if (! $isp->esPrincipal()) {
            $isp->planTv();
        }
    }

    /**
     * Roles por defecto con sus permisos (syncPermissions: los deja exactamente
     * como la lista ROLES_POR_DEFECTO).
     */
    public static function sincronizarRoles(Isp $isp): void
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
