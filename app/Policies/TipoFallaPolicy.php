<?php

namespace App\Policies;

use App\Models\TipoFalla;
use App\Models\User;

/**
 * Catálogo de tipos de falla: ISP con tickets y permiso tickets.tipos
 * (Administrador). El Super Admin salta todo.
 */
class TipoFallaPolicy
{
    private function puede(User $user): bool
    {
        return (bool) $user->isp?->tieneTickets() && $user->can('tickets.tipos');
    }

    public function viewAny(User $user): bool
    {
        return $this->puede($user);
    }

    public function create(User $user): bool
    {
        return $this->puede($user);
    }

    public function update(User $user, TipoFalla $tipo): bool
    {
        return $this->puede($user) && $tipo->isp_id === $user->isp_id;
    }

    public function delete(User $user, TipoFalla $tipo): bool
    {
        return $this->update($user, $tipo);
    }
}
