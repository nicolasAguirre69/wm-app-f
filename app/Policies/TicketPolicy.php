<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

/**
 * Tickets de soporte: solo en la ISP principal y las de "Gestión completa",
 * con los permisos tickets.* de cada rol. El Super Admin salta todo
 * (Gate::before) y ve los tickets de todas las ISP.
 */
class TicketPolicy
{
    private function modulo(User $user): bool
    {
        return (bool) $user->isp?->tieneTickets();
    }

    private function propio(User $user, Ticket $ticket): bool
    {
        return $ticket->isp_id === $user->isp_id;
    }

    public function viewAny(User $user): bool
    {
        return $this->modulo($user) && $user->can('tickets.ver');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->viewAny($user) && $this->propio($user, $ticket);
    }

    public function create(User $user): bool
    {
        return $this->modulo($user) && $user->can('tickets.crear');
    }

    public function comentar(User $user, Ticket $ticket): bool
    {
        return $this->modulo($user) && $user->can('tickets.comentar') && $this->propio($user, $ticket);
    }

    /** Cambiar prioridad, tipo de falla o fecha de visita. */
    public function update(User $user, Ticket $ticket): bool
    {
        return $this->modulo($user) && $user->can('tickets.editar') && $this->propio($user, $ticket);
    }

    /** Cerrar y reabrir. */
    public function cerrar(User $user, Ticket $ticket): bool
    {
        return $this->modulo($user) && $user->can('tickets.cerrar') && $this->propio($user, $ticket);
    }
}
