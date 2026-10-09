<?php

namespace App\Policies;

use App\Models\Pago;
use App\Models\User;

/**
 * Pagos y comprobantes: solo en la ISP principal y las de "Gestión completa",
 * con los permisos pagos.* de cada rol. El Super Admin salta todo
 * (Gate::before).
 */
class PagoPolicy
{
    private function modulo(User $user): bool
    {
        return (bool) $user->isp?->tienePagos();
    }

    public function viewAny(User $user): bool
    {
        return $this->modulo($user) && $user->can('pagos.ver');
    }

    public function view(User $user, Pago $pago): bool
    {
        return $this->viewAny($user) && $pago->isp_id === $user->isp_id;
    }

    public function create(User $user): bool
    {
        return $this->modulo($user) && $user->can('pagos.registrar');
    }

    public function anular(User $user, Pago $pago): bool
    {
        return $this->modulo($user) && $user->can('pagos.anular') && $pago->isp_id === $user->isp_id;
    }
}
