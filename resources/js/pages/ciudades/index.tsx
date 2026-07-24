import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Ciudad, type Paginated, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ciudades', href: '/ciudades' },
];

interface Filtros {
    search?: string;
    sort?: string;
    direction?: string;
}

interface Props {
    ciudades: Paginated<Ciudad>;
    filtros: Filtros;
}

export default function CiudadesIndex({ ciudades, filtros }: Props) {
    const { can } = usePermissions();
    const { flash } = usePage<SharedData>().props;
    const [search, setSearch] = useState(filtros.search ?? '');

    // Enviar búsqueda al servidor (recarga solo los datos, no la página).
    const buscar = (e: FormEvent) => {
        e.preventDefault();
        router.get('/ciudades', { search }, { preserveState: true, replace: true });
    };

    // Alternar orden de una columna: asc -> desc -> asc...
    const ordenarPor = (columna: string) => {
        const direction = filtros.sort === columna && filtros.direction === 'asc' ? 'desc' : 'asc';
        router.get('/ciudades', { ...filtros, sort: columna, direction }, { preserveState: true, replace: true });
    };

    // Icono de orden según el estado de la columna.
    const iconoOrden = (columna: string) => {
        if (filtros.sort !== columna) return <ArrowUpDown className="ml-1 inline size-3.5 opacity-50" />;
        return filtros.direction === 'asc' ? (
            <ArrowUp className="ml-1 inline size-3.5" />
        ) : (
            <ArrowDown className="ml-1 inline size-3.5" />
        );
    };

    const eliminar = (ciudad: Ciudad) => {
        if (confirm(`¿Eliminar la ciudad "${ciudad.nombre}"?`)) {
            router.delete(`/ciudades/${ciudad.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ciudades" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Notificación de éxito */}
                {flash.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                {/* Encabezado: título + botón crear */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Ciudades</h1>
                        <p className="text-muted-foreground text-sm">Administra las ciudades de tu ISP.</p>
                    </div>
                    {can('ciudades.crear') && (
                        <Button asChild>
                            <Link href="/ciudades/create">
                                <Plus className="size-4" /> Nueva ciudad
                            </Link>
                        </Button>
                    )}
                </div>

                {/* Búsqueda */}
                <form onSubmit={buscar} className="flex gap-2">
                    <Input
                        placeholder="Buscar por nombre..."
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        className="max-w-sm"
                    />
                    <Button type="submit" variant="secondary">
                        Buscar
                    </Button>
                    {filtros.search && (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => {
                                setSearch('');
                                router.get('/ciudades', {}, { preserveState: true, replace: true });
                            }}
                        >
                            Limpiar
                        </Button>
                    )}
                </form>

                {/* Tabla */}
                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    <button
                                        type="button"
                                        onClick={() => ordenarPor('nombre')}
                                        className="flex items-center font-medium"
                                    >
                                        Nombre {iconoOrden('nombre')}
                                    </button>
                                </TableHead>
                                <TableHead className="w-32 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {ciudades.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={2} className="text-muted-foreground py-8 text-center">
                                        No hay ciudades registradas.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                ciudades.data.map((ciudad) => (
                                    <TableRow key={ciudad.id}>
                                        <TableCell className="font-medium">{ciudad.nombre}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                {can('ciudades.editar') && (
                                                    <Button asChild variant="ghost" size="icon">
                                                        <Link href={`/ciudades/${ciudad.id}/edit`}>
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('ciudades.eliminar') && (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() => eliminar(ciudad)}
                                                    >
                                                        <Trash2 className="text-destructive size-4" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* Pie: total + paginación */}
                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {ciudades.total} {ciudades.total === 1 ? 'ciudad' : 'ciudades'} en total
                    </p>
                    <Pagination links={ciudades.links} />
                </div>
            </div>
        </AppLayout>
    );
}
