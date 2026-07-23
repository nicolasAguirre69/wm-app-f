<?php

namespace App\Models;

use App\Enums\TipoIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Isp extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Atributos asignables masivamente.
     *
     * $fillable protege contra "mass assignment": solo estos campos pueden
     * llenarse de golpe con Isp::create($request->all()). Evita que un
     * atacante inyecte campos que no debería (ej. is_admin) vía formulario.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'tipo',
        'activo',
    ];

    /**
     * Conversión automática de tipos.
     *
     * - 'tipo' se convierte al Enum TipoIsp: al leer da un objeto Enum,
     *   al guardar solo acepta valores válidos del Enum.
     * - 'activo' se maneja como booleano real (true/false), no como 1/0.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoIsp::class,
            'activo' => 'boolean',
        ];
    }
}
