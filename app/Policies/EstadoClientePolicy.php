<?php

namespace App\Policies;

use App\Models\EstadoCliente;
use App\Models\User;

/**
 * Policy de EstadoCliente: permiso 'estados.*' + pertenencia al ISP.
 */
class EstadoClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('estados.ver');
    }

    public function view(User $user, EstadoCliente $estado): bool
    {
        return $user->can('estados.ver')
            && $estado->isp_id === $user->isp_id;
    }

    public function create(User $user): bool
    {
        return $user->can('estados.crear');
    }

    public function update(User $user, EstadoCliente $estado): bool
    {
        return $user->can('estados.editar')
            && $estado->isp_id === $user->isp_id;
    }

    public function delete(User $user, EstadoCliente $estado): bool
    {
        return $user->can('estados.eliminar')
            && $estado->isp_id === $user->isp_id;
    }
}
