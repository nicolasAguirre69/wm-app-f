import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated, type SharedData, type TipoCatalogo } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Estados de cliente', href: '/estados' }];

interface Filtros {
    search?: string;
    sort?: string;
    direction?: string;
}

interface Props {
    estados: Paginated<TipoCatalogo>;
    filtros: Filtros;
}

export default function EstadosIndex({ estados, filtros }: Props) {
    const { can } = usePermissions();
    const { flash } = usePage<SharedData>().props;
    const [search, setSearch] = useState(filtros.search ?? '');

    const buscar = (e: FormEvent) => {
        e.preventDefault();
        router.get('/estados', { search }, { preserveState: true, replace: true });
    };

    const ordenarPor = (columna: string) => {
        const direction = filtros.sort === columna && filtros.direction === 'asc' ? 'desc' : 'asc';
        router.get('/estados', { ...filtros, sort: columna, direction }, { preserveState: true, replace: true });
    };

    const iconoOrden = (columna: string) => {
        if (filtros.sort !== columna) return <ArrowUpDown className="ml-1 inline size-3.5 opacity-50" />;
        return filtros.direction === 'asc' ? (
            <ArrowUp className="ml-1 inline size-3.5" />
        ) : (
            <ArrowDown className="ml-1 inline size-3.5" />
        );
    };

    const eliminar = (estado: TipoCatalogo) => {
        if (confirm(`¿Eliminar el estado "${estado.nombre}"?`)) {
            router.delete(`/estados/${estado.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Estados de cliente" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {flash.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Estados de cliente</h1>
                        <p className="text-muted-foreground text-sm">Administra los estados de tu ISP.</p>
                    </div>
                    {can('estados.crear') && (
                        <Button asChild>
                            <Link href="/estados/create">
                                <Plus className="size-4" /> Nuevo estado
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
                                router.get('/estados', {}, { preserveState: true, replace: true });
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
                                <TableHead className="w-32 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {estados.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={2} className="text-muted-foreground py-8 text-center">
                                        No hay estados registrados.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                estados.data.map((estado) => (
                                    <TableRow key={estado.id}>
                                        <TableCell className="font-medium">{estado.nombre}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                {can('estados.editar') && (
                                                    <Button asChild variant="ghost" size="icon">
                                                        <Link href={`/estados/${estado.id}/edit`}>
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('estados.eliminar') && (
                                                    <Button variant="ghost" size="icon" onClick={() => eliminar(estado)}>
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
                        {estados.total} {estados.total === 1 ? 'estado' : 'estados'} en total
                    </p>
                    <Pagination links={estados.links} />
                </div>
            </div>
        </AppLayout>
    );
}
