<?php

namespace App\Traits;

use Hashids\Hashids;

/**
 * Oculta el id numérico en las URLs. El modelo sigue usando su id interno,
 * pero en las rutas aparece un código corto y opaco (ej: "x7Gk2aQ1r").
 *
 *   - getRouteKey()        -> lo que se pone en la URL.
 *   - resolveRouteBinding()-> recupera el modelo desde ese código, respetando
 *                             los scopes (aislamiento por ISP y borrados lógicos).
 *   - hashid (accesor)     -> se expone al frontend para construir las URLs.
 *
 * Requiere el paquete hashids/hashids (composer require hashids/hashids).
 */
trait HasHashid
{
    protected static ?Hashids $hashidsInstance = null;

    protected static function hashids(): Hashids
    {
        if (static::$hashidsInstance === null) {
            // Salt derivado del APP_KEY + la clase: los códigos no son
            // predecibles y son distintos por modelo (id 5 de Cliente != id 5 de Plan).
            static::$hashidsInstance = new Hashids((string) config('app.key').static::class, 10);
        }

        return static::$hashidsInstance;
    }

    /**
     * Codifica el id para las URLs.
     */
    public function getRouteKey(): string
    {
        return static::hashids()->encode($this->getKey());
    }

    /**
     * Recupera el modelo a partir del código de la URL. Usa la consulta normal
     * del modelo, así que sigue aplicando el IspScope y los soft deletes.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $decoded = static::hashids()->decode((string) $value);

        if (empty($decoded)) {
            return null;
        }

        return $this->where($this->getKeyName(), $decoded[0])->first();
    }

    /**
     * Accesor expuesto al frontend (aparece en el JSON como "hashid").
     */
    public function getHashidAttribute(): string
    {
        return static::hashids()->encode($this->getKey());
    }

    /**
     * Decodifica un hashid de vuelta al id numérico (para filtros por query
     * string, donde no hay route binding). Devuelve null si no es válido.
     */
    public static function decodeHashid(?string $hash): ?int
    {
        if ($hash === null || $hash === '') {
            return null;
        }

        $decoded = static::hashids()->decode($hash);

        return empty($decoded) ? null : (int) $decoded[0];
    }
}
