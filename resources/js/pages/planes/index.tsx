import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated, type Plan, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Planes', href: '/planes' }];

interface Filtros {
    search?: string;
    sort?: string;
    direction?: string;
}

interface Props {
    planes: Paginated<Plan>;
    filtros: Filtros;
}

// Formatea el valor como moneda colombiana.
const formatoMoneda = (valor: string) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(valor));

export default function PlanesIndex({ planes, filtros }: Props) {
    const { can } = usePermissions();
    const { flash } = usePage<SharedData>().props;
    const [search, setSearch] = useState(filtros.search ?? '');

    const buscar = (e: FormEvent) => {
        e.preventDefault();
        router.get('/planes', { search }, { preserveState: true, replace: true });
    };

    const ordenarPor = (columna: string) => {
        const direction = filtros.sort === columna && filtros.direction === 'asc' ? 'desc' : 'asc';
        router.get('/planes', { ...filtros, sort: columna, direction }, { preserveState: true, replace: true });
    };

    const iconoOrden = (columna: string) => {
        if (filtros.sort !== columna) return <ArrowUpDown className="ml-1 inline size-3.5 opacity-50" />;
        return filtros.direction === 'asc' ? (
            <ArrowUp className="ml-1 inline size-3.5" />
        ) : (
            <ArrowDown className="ml-1 inline size-3.5" />
        );
    };

    const eliminar = (plan: Plan) => {
        if (confirm(`¿Eliminar este plan?`)) {
            router.delete(`/planes/${plan.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Planes" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {flash.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Planes</h1>
                        <p className="text-muted-foreground text-sm">Administra los planes de tu ISP.</p>
                    </div>
                    {can('planes.crear') && (
                        <Button asChild>
                            <Link href="/planes/create">
                                <Plus className="size-4" /> Nuevo plan
                            </Link>
                        </Button>
                    )}
                </div>

                <form onSubmit={buscar} className="flex gap-2">
                    <Input
                        placeholder="Buscar por tipo de plan o servicio..."
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
                                router.get('/planes', {}, { preserveState: true, replace: true });
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
                                <TableHead>Tipo de plan</TableHead>
                                <TableHead>Servicio</TableHead>
                                <TableHead>Mbps</TableHead>
                                <TableHead>
                                    <button
                                        type="button"
                                        onClick={() => ordenarPor('valor')}
                                        className="flex items-center font-medium"
                                    >
                                        Valor {iconoOrden('valor')}
                                    </button>
                                </TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-32 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {planes.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">
                                        No hay planes registrados.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                planes.data.map((plan) => (
                                    <TableRow key={plan.id}>
                                        <TableCell className="font-medium">{plan.tipo_plan?.nombre ?? '—'}</TableCell>
                                        <TableCell>{plan.tipo_servicio?.nombre ?? '—'}</TableCell>
                                        <TableCell>{plan.cantidad ?? '—'}</TableCell>
                                        <TableCell>{formatoMoneda(plan.valor)}</TableCell>
                                        <TableCell>
                                            <Badge variant={plan.activo ? 'default' : 'secondary'}>
                                                {plan.activo ? 'Activo' : 'Inactivo'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                {can('planes.editar') && (
                                                    <Button asChild variant="ghost" size="icon">
                                                        <Link href={`/planes/${plan.id}/edit`}>
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>
                                                )}
                                                {can('planes.eliminar') && (
                                                    <Button variant="ghost" size="icon" onClick={() => eliminar(plan)}>
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
                        {planes.total} {planes.total === 1 ? 'plan' : 'planes'} en total
                    </p>
                    <Pagination links={planes.links} />
                </div>
            </div>
        </AppLayout>
    );
}
