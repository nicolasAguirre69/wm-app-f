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

    /**
     * Hooks del modelo. Antes de guardar, normalizamos los nombres a formato
     * "Título" (Primera Letra En Mayúscula) para que no dependa de cómo los
     * escriba el usuario (TODO MAYÚSCULAS o todo minúsculas).
     */
    protected static function booted(): void
    {
        static::saving(function (Cliente $cliente): void {
            foreach (['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido'] as $campo) {
                $cliente->{$campo} = self::normalizarNombre($cliente->{$campo});
            }
        });
    }

    /**
     * Pone un nombre en formato "Título": cada palabra con la primera letra en
     * mayúscula y el resto en minúscula. Respeta conectores comunes en español
     * (de, del, la, los, y...) dejándolos en minúscula si no van al inicio.
     * Usa funciones mb_ para no romper las tildes ni la ñ.
     */
    public static function normalizarNombre(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        // Colapsa espacios repetidos y recorta extremos.
        $valor = trim(preg_replace('/\s+/', ' ', $valor) ?? '');

        if ($valor === '') {
            return null;
        }

        $conectores = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'do', 'van', 'von'];
        $palabras = explode(' ', mb_strtolower($valor, 'UTF-8'));

        $normalizadas = array_map(function (string $palabra, int $i) use ($conectores): string {
            if ($i > 0 && in_array($palabra, $conectores, true)) {
                return $palabra;
            }

            return mb_strtoupper(mb_substr($palabra, 0, 1, 'UTF-8'), 'UTF-8')
                .mb_substr($palabra, 1, null, 'UTF-8');
        }, $palabras, array_keys($palabras));

        return implode(' ', $normalizadas);
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

    /**
     * Comentarios/observaciones del cliente.
     */
    public function comentarios(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comentario::class);
    }
}
