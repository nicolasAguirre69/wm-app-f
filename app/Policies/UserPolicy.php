<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gestión de usuarios.
 *
 * - Super Admin: gestiona usuarios de cualquier ISP (pasa por Gate::before).
 * - Usuario con permiso 'usuarios.*': gestiona SOLO usuarios de su propio ISP.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('usuarios.ver');
    }

    public function view(User $user, User $modelo): bool
    {
        return $user->can('usuarios.ver') && $modelo->isp_id === $user->isp_id;
    }

    public function create(User $user): bool
    {
        return $user->can('usuarios.crear');
    }

    public function update(User $user, User $modelo): bool
    {
        return $user->can('usuarios.editar') && $modelo->isp_id === $user->isp_id;
    }

    public function delete(User $user, User $modelo): bool
    {
        // No puede eliminarse a sí mismo.
        if ($user->id === $modelo->id) {
            return false;
        }

        return $user->can('usuarios.eliminar') && $modelo->isp_id === $user->isp_id;
    }
}
