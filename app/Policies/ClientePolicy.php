<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

/**
 * Policy de Cliente: permiso 'clientes.*' + pertenencia al ISP.
 *
 * El Super Admin salta todo vía Gate::before. Eso hace que el método
 * marcarFacturable() solo se evalúe para NO-super-admins, y para ellos
 * siempre niega: así solo el Super Admin puede tocar el campo facturable.
 */
class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clientes.ver');
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $user->can('clientes.ver')
            && $cliente->isp_id === $user->isp_id;
    }

    public function create(User $user): bool
    {
        return $user->can('clientes.crear');
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->can('clientes.editar')
            && $cliente->isp_id === $user->isp_id;
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->can('clientes.eliminar')
            && $cliente->isp_id === $user->isp_id;
    }

    /**
     * Marcar/desmarcar como facturable: EXCLUSIVO del Super Admin.
     * Para cualquier otro usuario (que es quien llega aquí, porque el Super
     * Admin salta por Gate::before) siempre se niega.
     */
    public function marcarFacturable(User $user, Cliente $cliente): bool
    {
        return false;
    }
}
