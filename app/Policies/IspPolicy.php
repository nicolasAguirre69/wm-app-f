<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gestión de ISPs: EXCLUSIVA del Super Admin.
 * El Super Admin pasa por Gate::before; para los demás se niega todo.
 */
class IspPolicy
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
