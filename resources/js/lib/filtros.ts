import { router } from '@inertiajs/react';

type Filtros = Record<string, string | undefined>;

// Codifica un objeto ya "limpio" a un token opaco (base64 seguro para Unicode).
function codificar(obj: Record<string, string>): string {
    return btoa(String.fromCharCode(...new TextEncoder().encode(JSON.stringify(obj))));
}

/**
 * Navega a `url` empaquetando los filtros en un único parámetro opaco ?f=,
 * para que la URL no muestre nombres ni valores legibles.
 *
 * @param extra  Parámetros que van sueltos (ej. comentarios_de, ya opacos).
 * @param opts   Opciones de Inertia (only, preserveScroll, etc.).
 */
export function navegarConFiltros(
    url: string,
    filtros: Filtros,
    extra: Record<string, string> = {},
    opts: Record<string, unknown> = {},
): void {
    // Quitamos claves vacías para no arrastrar basura en el token.
    const limpio = Object.fromEntries(
        Object.entries(filtros).filter(([, v]) => v !== undefined && v !== ''),
    ) as Record<string, string>;

    const params: Record<string, string> = Object.keys(limpio).length
        ? { f: codificar(limpio), ...extra }
        : { ...extra };

    router.get(url, params, { preserveState: true, replace: true, ...opts });
}
