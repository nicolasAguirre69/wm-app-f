<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evento del historial de un ticket (trazabilidad). Solo se crean: no se
 * editan ni se borran.
 */
class TicketEvento extends Model
{
    protected $table = 'ticket_eventos';

    public const UPDATED_AT = null; // solo fecha de creación

    protected $fillable = ['ticket_id', 'user_id', 'tipo', 'contenido'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
