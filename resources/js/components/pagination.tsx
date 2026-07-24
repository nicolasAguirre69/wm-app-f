import { cn } from '@/lib/utils';
import { type PaginationLink } from '@/types';
import { Link } from '@inertiajs/react';

interface PaginationProps {
    links: PaginationLink[];
}

/**
 * Paginación reutilizable. Recibe el array `links` que genera el paginador
 * de Laravel y lo renderiza. Usa <Link> de Inertia para navegar sin recargar
 * la página completa (mantiene la SPA).
 */
export function Pagination({ links }: PaginationProps) {
    // Si solo hay 3 enlaces (« anterior, 1, siguiente »), no vale la pena mostrar.
    if (links.length <= 3) {
        return null;
    }

    return (
        <nav className="flex flex-wrap items-center gap-1">
            {links.map((link, index) =>
                link.url === null ? (
                    <span
                        key={index}
                        className="text-muted-foreground px-3 py-1.5 text-sm"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        className={cn(
                            'rounded-md px-3 py-1.5 text-sm transition-colors',
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'hover:bg-accent hover:text-accent-foreground',
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </nav>
    );
}
