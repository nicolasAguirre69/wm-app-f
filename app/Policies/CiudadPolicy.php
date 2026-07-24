<?php

namespace App\Policies;

use App\Models\Ciudad;
use App\Models\User;

/**
 * Policy de Ciudad: autorización por permiso + pertenencia al ISP.
 *
 * Nota: el Super Admin NO pasa por aquí; el Gate::before (AppServiceProvider)
 * lo autoriza antes de evaluar cualquier método de esta Policy.
 */
class CiudadPolicy
{
    /**
     * ¿Puede ver el listado de ciudades?
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ciudades.ver');
    }

    /**
     * ¿Puede ver una ciudad concreta?
     * Permiso + que la ciudad sea de su ISP.
     */
    public function view(User $user, Ciudad $ciudad): bool
    {
        return $user->can('ciudades.ver')
            && $ciudad->isp_id === $user->isp_id;
    }

    /**
     * ¿Puede crear ciudades?
     */
    public function create(User $user): bool
    {
        return $user->can('ciudades.crear');
    }

    /**
     * ¿Puede editar esta ciudad?
     */
    public function update(User $user, Ciudad $ciudad): bool
    {
        return $user->can('ciudades.editar')
            && $ciudad->isp_id === $user->isp_id;
    }

    /**
     * ¿Puede eliminar esta ciudad?
     */
    public function delete(User $user, Ciudad $ciudad): bool
    {
        return $user->can('ciudades.eliminar')
            && $ciudad->isp_id === $user->isp_id;
    }
}
