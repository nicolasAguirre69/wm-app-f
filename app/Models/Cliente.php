<?php

namespace App\Models;

use App\Enums\TipoContribuyente;
use App\Enums\TipoIdentificacion;
use App\Traits\BelongsToIsp;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cliente: el módulo central. Catálogo POR ISP.
 */
class Cliente extends Model
{
    use BelongsToIsp, HasFactory, SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'isp_id',
        'codigo_cliente',
        'tipo_identificacion',
        'identificacion',
        'tipo_contribuyente',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'telefono_1',
        'telefono_2',
        'correo',
        'ciudad_id',
        'barrio_id',
        'direccion',
        'plan_id',
        'estado_id',
        'fecha_instalacion',
        'dia_corte',
        'usuario_creador_id',
        'documento_digitalizado',
        'facturable',
        'motivo_no_facturable',
    ];

    protected function casts(): array
    {
        return [
            'tipo_identificacion' => TipoIdentificacion::class,
            'tipo_contribuyente' => TipoContribuyente::class,
            'fecha_instalacion' => 'date',
            'dia_corte' => 'integer',
            'facturable' => 'boolean',
        ];
    }

    // --- Relaciones ---

    public function ciudad(): BelongsTo
    {
        return $this->belongsTo(Ciudad::class);
    }

    public function barrio(): BelongsTo
    {
        return $this->belongsTo(Barrio::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(EstadoCliente::class, 'estado_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_creador_id');
    }
}
