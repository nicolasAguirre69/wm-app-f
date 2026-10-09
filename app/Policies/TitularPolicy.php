<?php

namespace App\Policies;

use App\Models\Titular;
use App\Models\User;

/**
 * Policy de Titular (la persona): usa los mismos permisos de clientes
 * ('clientes.*') + pertenencia al ISP. El Super Admin salta por Gate::before.
 */
class TitularPolicy
{
    public function update(User $user, Titular $titular): bool
    {
        return $user->can('clientes.editar')
            && $titular->isp_id === $user->isp_id;
    }
}
