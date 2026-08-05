<?php

namespace App\Models;

use App\Traits\BelongsToIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EstadoCliente: catálogo POR ISP (Activo, Suspendido, Retirado, Pendiente).
 */
class EstadoCliente extends Model
{
    use BelongsToIsp, HasFactory, SoftDeletes;

    protected $table = 'estados_cliente';

    protected $fillable = [
        'isp_id',
        'nombre',
        'color',
    ];
}
