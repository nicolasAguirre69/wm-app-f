<?php

namespace App\Policies;

use App\Models\User;

/**
 * Ciudades es un catálogo GLOBAL: solo lo administra el Super Admin.
 *
 * El Super Admin pasa por Gate::before (autoriza todo antes de llegar aquí).
 * Para cualquier otro usuario, estos métodos niegan: los ISP seleccionan
 * ciudades en sus formularios, pero no las crean ni editan.
 */
class CiudadPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return false;
    }

    public function delete(User $user): bool
    {
        return false;
    }
}
