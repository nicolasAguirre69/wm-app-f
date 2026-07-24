import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type Barrio, type BreadcrumbItem, type Paginated, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Barrios', href: '/barrios' }];

interface Filtros {
    search?: string;
    sort?: string;
    direction?: string;
}

interface Props {
    barrios: Paginated<Barrio>;
    filtros: Filtros;
}

export default function BarriosIndex({ barrios, filtros }: Props) {
    const { can } = usePermissions();
    const { flash } = usePage<SharedData>().props;
    const [search, setSearch] = useState(filtros.search ?? '');

    const buscar = (e: FormEvent) => {
        e.preventDefault();
        router.get('/barrios', { search }, { preserveState: true, replace: true });
    };

    const ordenarPor = (columna: string) => {
        const direction = filtros.sort === columna && filtros.direction === 'asc' ? 'desc' : 'asc';
        router.get('/barrios', { ...filtros, sort: columna, direction }, { preserveState: true, replace: true });
    };

    const iconoOrden = (columna: string) => {
        if (filtros.sort !== columna) return <ArrowUpDown className="ml-1 inline size-3.5 opacity-50" />;
        return filtros.direction === 'asc' ? (
            <ArrowUp className="ml-1 inline size-3.5" />
        ) : (
            <ArrowDown className="ml-1 inline size-3.5" />
        );
    };

    const eliminar = (barrio: Barrio) => {
        if (confirm(`¿Eliminar el barrio "${barrio.nombre}"?`)) {
            router.delete(`/barrios/${barrio.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Barrios" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {flash.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Barrios</h1>
                        <p className="text-muted-foreground text-sm">Administra los barrios de tu ISP.</p>
                    </div>
                    {can('barrios.crear') && (
                        <Button asChild>
                            <Link href="/barrios/create">
                                <Plus className="size-4" /> Nuevo barrio
                            </Link>
                        </Button>
                    )}
                </div>

                <form onSubmit={buscar} className="flex gap-2">
                    <Input
                        placeholder="Buscar por nombre, prefijo o red..."
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
                                router.get('/barrios', {}, { preserveState: true, replace: true });
                            }}
                        >
                            Limpiar
                        </Button>
                    )}
                </form>

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
                                <TableHead>Ciudad</TableHead>
                                <TableHead>Prefijo</TableHead>
                                <TableHead>Redes</TableHead>
                                <TableHead className="w-32 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {barrios.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-muted-foreground py-8 text-center">
                                        No hay barrios registrados.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                barrios.data.map((barrio) => (
                                    <TableRow key={barrio.id}>
                                        <TableCell className="font-medium">{barrio.nombre}</TableCell>
                                        <TableCell>{barrio.ciudad?.nombre ?? '—'}</TableCell>
                                        <TableCell>{barrio.prefijo}</TableCell>
                                        <TableCell>{barrio.redes_count ?? 0}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                {can('barrios.editar') && (
                                                    <Button asChild variant="ghost" size="icon">
                                                        <Link href={`/barrios/${barrio.id}/edit`}>
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('barrios.eliminar') && (
                                                    <Button variant="ghost" size="icon" onClick={() => eliminar(barrio)}>
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

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {barrios.total} {barrios.total === 1 ? 'barrio' : 'barrios'} en total
                    </p>
                    <Pagination links={barrios.links} />
                </div>
            </div>
        </AppLayout>
    );
}
