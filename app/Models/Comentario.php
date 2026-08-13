<?php

namespace App\Models;

use App\Enums\TipoComentario;
use App\Traits\BelongsToIsp;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comentario/observación de un cliente. Aislado por ISP vía BelongsToIsp.
 */
class Comentario extends Model
{
    use BelongsToIsp, HasHashid, SoftDeletes;

    protected $table = 'comentarios';

    protected $fillable = [
        'isp_id',
        'cliente_id',
        'user_id',
        'tipo',
        'contenido',
    ];

    protected function casts(): array
    {
        return [
            'tipo' => TipoComentario::class,
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Autor del comentario.
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
