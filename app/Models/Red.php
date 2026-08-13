<?php

namespace App\Models;

use App\Traits\BelongsToIsp;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Red: catálogo POR ISP. Pertenece a un barrio.
 * Jerarquía: Ciudad -> Barrio -> Red.
 *
 * La red solo guarda `numero` (ej. "1"). El nombre completo ("1USM") se
 * calcula combinando el número con el prefijo del barrio (accessor `nombre`).
 */
class Red extends Model
{
    use BelongsToIsp, HasFactory, HasHashid, SoftDeletes;

    protected $table = 'redes';

    protected $fillable = [
        'isp_id',
        'barrio_id',
        'numero',
    ];

    // Incluye los atributos calculados 'nombre' y 'hashid' al serializar a JSON.
    protected $appends = ['nombre', 'hashid'];

    /**
     * Relación: la red pertenece a un barrio.
     */
    public function barrio(): BelongsTo
    {
        return $this->belongsTo(Barrio::class);
    }

    /**
     * Accessor: nombre completo = número + prefijo del barrio (ej. "1USM").
     * Si el barrio no está cargado, devuelve solo el número (defensa).
     */
    protected function nombre(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->numero.($this->barrio?->prefijo ?? ''),
        );
    }
}
