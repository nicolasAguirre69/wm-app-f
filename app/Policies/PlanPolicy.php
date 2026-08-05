<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

/**
 * Policy de Plan: permiso + pertenencia al ISP. Super Admin la salta.
 */
class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('planes.ver');
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->can('planes.ver')
            && $plan->isp_id === $user->isp_id;
    }

    public function create(User $user): bool
    {
        return $user->can('planes.crear');
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->can('planes.editar')
            && $plan->isp_id === $user->isp_id;
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->can('planes.eliminar')
            && $plan->isp_id === $user->isp_id;
    }
}
