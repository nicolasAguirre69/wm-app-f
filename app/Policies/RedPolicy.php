<?php

namespace App\Policies;

use App\Models\Red;
use App\Models\User;

/**
 * Policy de Red: permiso + pertenencia al ISP. Super Admin la salta.
 */
class RedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('redes.ver');
    }

    public function view(User $user, Red $red): bool
    {
        return $user->can('redes.ver')
            && $red->isp_id === $user->isp_id;
    }

    public function create(User $user): bool
    {
        return $user->can('redes.crear');
    }

    public function update(User $user, Red $red): bool
    {
        return $user->can('redes.editar')
            && $red->isp_id === $user->isp_id;
    }

    public function delete(User $user, Red $red): bool
    {
        return $user->can('redes.eliminar')
            && $red->isp_id === $user->isp_id;
    }
}
