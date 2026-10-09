<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

/**
 * Policy de Plan: permiso + pertenencia al ISP. Super Admin la salta.
 */
class PlanPolicy
{
    /**
     * Las ISP "Solo TV" no administran planes (su plan TV lo asigna el sistema).
     * La principal y las de "Gestión completa" sí.
     */
    private function gestionaPlanes(User $user): bool
    {
        return (bool) $user->isp?->tienePlanesPropios();
    }

    public function viewAny(User $user): bool
    {
        return $this->gestionaPlanes($user) && $user->can('planes.ver');
    }

    public function view(User $user, Plan $plan): bool
    {
        return $this->gestionaPlanes($user) && $user->can('planes.ver')
            && $plan->isp_id === $user->isp_id;
    }

    public function create(User $user): bool
    {
        return $this->gestionaPlanes($user) && $user->can('planes.crear');
    }

    public function update(User $user, Plan $plan): bool
    {
        return $this->gestionaPlanes($user) && $user->can('planes.editar')
            && $plan->isp_id === $user->isp_id;
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $this->gestionaPlanes($user) && $user->can('planes.eliminar')
            && $plan->isp_id === $user->isp_id;
    }
}
