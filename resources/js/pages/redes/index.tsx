import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated, type Red, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Redes', href: '/redes' }];

interface Filtros {
    search?: string;
    sort?: string;
    direction?: string;
}

interface Props {
    redes: Paginated<Red>;
    filtros: Filtros;
}

export default function RedesIndex({ redes, filtros }: Props) {
    const { can } = usePermissions();
    const { flash } = usePage<SharedData>().props;
    const [search, setSearch] = useState(filtros.search ?? '');

    const buscar = (e: FormEvent) => {
        e.preventDefault();
        router.get('/redes', { search }, { preserveState: true, replace: true });
    };

    const ordenarPor = (columna: string) => {
        const direction = filtros.sort === columna && filtros.direction === 'asc' ? 'desc' : 'asc';
        router.get('/redes', { ...filtros, sort: columna, direction }, { preserveState: true, replace: true });
    };

    const iconoOrden = (columna: string) => {
        if (filtros.sort !== columna) return <ArrowUpDown className="ml-1 inline size-3.5 opacity-50" />;
        return filtros.direction === 'asc' ? (
            <ArrowUp className="ml-1 inline size-3.5" />
        ) : (
            <ArrowDown className="ml-1 inline size-3.5" />
        );
    };

    const eliminar = (red: Red) => {
        if (confirm(`¿Eliminar la red "${red.nombre}"?`)) {
            router.delete(`/redes/${red.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Redes" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {flash.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Redes</h1>
                        <p className="text-muted-foreground text-sm">Administra las redes de cada barrio.</p>
                    </div>
                    {can('redes.crear') && (
                        <Button asChild>
                            <Link href="/redes/create">
                                <Plus className="size-4" /> Nueva red
                            </Link>
                        </Button>
                    )}
                </div>

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
                                router.get('/redes', {}, { preserveState: true, replace: true });
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
                                        onClick={() => ordenarPor('numero')}
                                        className="flex items-center font-medium"
                                    >
                                        Nombre {iconoOrden('numero')}
                                    </button>
                                </TableHead>
                                <TableHead>Barrio</TableHead>
                                <TableHead>Ciudad</TableHead>
                                <TableHead className="w-32 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {redes.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} className="text-muted-foreground py-8 text-center">
                                        No hay redes registradas.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                redes.data.map((red) => (
                                    <TableRow key={red.id}>
                                        <TableCell className="font-medium">{red.nombre}</TableCell>
                                        <TableCell>{red.barrio?.nombre ?? '—'}</TableCell>
                                        <TableCell>{red.barrio?.ciudad?.nombre ?? '—'}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                {can('redes.editar') && (
                                                    <Button asChild variant="ghost" size="icon">
                                                        <Link href={`/redes/${red.id}/edit`}>
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('redes.eliminar') && (
                                                    <Button variant="ghost" size="icon" onClick={() => eliminar(red)}>
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
                        {redes.total} {redes.total === 1 ? 'red' : 'redes'} en total
                    </p>
                    <Pagination links={redes.links} />
                </div>
            </div>
        </AppLayout>
    );
}
