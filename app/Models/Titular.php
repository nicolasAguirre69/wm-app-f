<?php

namespace App\Models;

use App\Enums\TipoContribuyente;
use App\Enums\TipoIdentificacion;
use App\Models\Scopes\IspScope;
use App\Traits\BelongsToIsp;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Titular: la PERSONA (o empresa) dueña de uno o varios servicios.
 *
 * Antes estos datos vivían repetidos en cada fila de `clientes`; ahora una
 * persona se guarda una sola vez por ISP (única por isp_id + identificacion)
 * y cada servicio (`clientes`) apunta a ella con titular_id. (3FN)
 */
class Titular extends Model
{
    use BelongsToIsp, HasHashid, SoftDeletes;

    protected $table = 'titulares';

    // Código ofuscado para las URLs (/titulares/{hashid}).
    protected $appends = ['hashid'];

    /**
     * Campos de la persona. Cliente usa esta misma lista para separar lo que
     * llega del formulario (persona vs. servicio).
     */
    public const CAMPOS = [
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
    ];

    protected $fillable = ['isp_id', ...self::CAMPOS];

    protected function casts(): array
    {
        return [
            'tipo_identificacion' => TipoIdentificacion::class,
            'tipo_contribuyente' => TipoContribuyente::class,
        ];
    }

    /**
     * Antes de guardar se normaliza todo lo que escribe el usuario, para que la
     * base no dependa de cómo se tecleó (y cumpla los CHECK de la tabla).
     */
    protected static function booted(): void
    {
        static::saving(function (Titular $titular): void {
            foreach (['primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido'] as $campo) {
                $titular->{$campo} = self::normalizarNombre($titular->{$campo});
            }

            // Si llegan dos números en un campo ("3201234567 / 3109876543"),
            // el segundo pasa a telefono_2 (si está libre).
            $partes = preg_split('/\s*[\/,;|]\s*/', (string) $titular->telefono_1) ?: [];
            if (count($partes) > 1) {
                $titular->telefono_1 = $partes[0];
                $titular->telefono_2 = $titular->telefono_2 ?: $partes[1];
            }

            // Teléfonos: solo dígitos ("320 123-4567" -> "3201234567").
            foreach (['telefono_1', 'telefono_2'] as $campo) {
                $digitos = preg_replace('/\D/', '', (string) $titular->{$campo});
                $titular->{$campo} = $digitos === '' ? null : $digitos;
            }

            // El tipo de contribuyente no se elige: sale del tipo de identificación
            // (CC -> Regimen Comun, NIT -> Gran Contribuyente, CE -> Tercero Exterior...).
            $titular->tipo_contribuyente = TipoContribuyente::paraIdentificacion($titular->tipo_identificacion);

            $titular->identificacion = trim((string) $titular->identificacion);

            $correo = mb_strtolower(trim((string) $titular->correo), 'UTF-8');
            $titular->correo = $correo === '' ? null : $correo;
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
        $valor = trim(preg_replace('/\s+/u', ' ', $valor) ?? '');

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

    /**
     * Devuelve el titular para estos datos dentro del ISP, creándolo o
     * actualizándolo. Reglas:
     *   1. Si ya existe un titular con esa identificación en el ISP, se usa ese
     *      (incluso si estaba borrado: se restaura) y se actualizan sus datos.
     *   2. Si no existe y el servicio que se edita tenía un titular que NO
     *      comparte con nadie más, se corrige ese mismo (cambio de cédula).
     *   3. Si no, se crea uno nuevo.
     *
     * @param  array<string, mixed>  $datos  Campos de self::CAMPOS.
     * @param  Titular|null  $actual  Titular actual del servicio que se edita.
     * @param  int|null  $clienteId  Servicio que se edita (para no contarse a sí mismo).
     */
    public static function sincronizar(int $ispId, array $datos, ?Titular $actual = null, ?int $clienteId = null): self
    {
        $datos = array_intersect_key($datos, array_flip(self::CAMPOS));
        $identificacion = trim((string) ($datos['identificacion'] ?? ''));

        $titular = static::withoutGlobalScope(IspScope::class)
            ->withTrashed()
            ->where('isp_id', $ispId)
            ->where('identificacion', $identificacion)
            ->first();

        if ($titular === null && $actual !== null) {
            $compartido = Cliente::withoutGlobalScope(IspScope::class)
                ->withTrashed()
                ->where('titular_id', $actual->id)
                ->when($clienteId, fn ($q) => $q->whereKeyNot($clienteId))
                ->exists();

            if (! $compartido) {
                $titular = $actual;
            }
        }

        $titular ??= new static;

        if ($titular->trashed()) {
            $titular->restore();
        }

        $titular->fill($datos);
        $titular->isp_id = $ispId;
        $titular->save();

        return $titular;
    }

    // --- Relaciones ---

    /**
     * Servicios contratados por este titular.
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }
}
