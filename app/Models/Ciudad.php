<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Ciudad: catálogo GLOBAL (compartido por todas las ISPs).
 * Una ciudad es geografía pura, sin datos propios de cada ISP.
 * La administra únicamente el Super Admin.
 */
class Ciudad extends Model
{
    use HasFactory, SoftDeletes;

    // 'Ciudad' se pluralizaría como 'ciudads' en inglés; lo corregimos.
    protected $table = 'ciudades';

    protected $fillable = [
        'nombre',
    ];

    /**
     * Relación: una ciudad tiene muchos barrios (de distintas ISPs).
     */
    public function barrios(): HasMany
    {
        return $this->hasMany(Barrio::class);
    }
}
