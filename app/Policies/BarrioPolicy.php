<?php

namespace App\Policies;

use App\Models\Barrio;
use App\Models\User;

/**
 * Policy de Barrio: autorización por permiso + pertenencia al ISP.
 * El Super Admin la salta vía Gate::before.
 */
class BarrioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('barrios.ver');
    }

    public function view(User $user, Barrio $barrio): bool
    {
        return $user->can('barrios.ver')
            && $barrio->isp_id === $user->isp_id;
    }

    public function create(User $user): bool
    {
        return $user->can('barrios.crear');
    }

    public function update(User $user, Barrio $barrio): bool
    {
        return $user->can('barrios.editar')
            && $barrio->isp_id === $user->isp_id;
    }

    public function delete(User $user, Barrio $barrio): bool
    {
        return $user->can('barrios.eliminar')
            && $barrio->isp_id === $user->isp_id;
    }
}
