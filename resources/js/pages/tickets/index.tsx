import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { navegarConFiltros } from '@/lib/filtros';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type OpcionSelect, type Paginated, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, LifeBuoy } from 'lucide-react';
import { FormEvent, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tickets de soporte', href: '/tickets' }];
const TODOS = 'todos';

export interface TicketResumen {
    id: number;
    hashid: string;
    numero: number;
    estado: 'abierto' | 'cerrado';
    estado_label: string;
    prioridad: string;
    prioridad_label: string;
    prioridad_color: string;
    tipo: string | null;
    isp: string | null;
    fecha: string;
    actualizado: string;
    fecha_visita: string | null;
    creador: string | null;
    servicio: { hashid: string | null; codigo: string | null; direccion: string | null; titular: string; identificacion: string | null };
}

export interface Prioridad {
    value: string;
    label: string;
    color: string;
}

interface Filtros {
    search?: string;
    estado?: string;
    prioridad?: string;
    tipo?: string;
    isp_id?: string;
}

interface Props {
    tickets: Paginated<TicketResumen>;
    filtros: Filtros;
    abiertos: number;
    urgentes: number;
    tiposFalla: { id: number; isp_id: number; nombre: string; hashid?: string }[];
    prioridades: Prioridad[];
    isps: (OpcionSelect & { hashid?: string })[] | null;
}

// Etiqueta de prioridad con su color.
export function PrioridadBadge({ label, color }: { label: string; color: string }) {
    return (
        <span
            className="inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-xs font-medium"
            style={{ borderColor: `${color}66`, backgroundColor: `${color}1a`, color }}
        >
            <span className="size-1.5 rounded-full" style={{ backgroundColor: color }} />
            {label}
        </span>
    );
}

export function EstadoTicketBadge({ estado, label }: { estado: string; label: string }) {
    const abierto = estado === 'abierto';
    return (
        <span
            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                abierto
                    ? 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
                    : 'bg-muted text-muted-foreground'
            }`}
        >
            {label}
        </span>
    );
}

export default function TicketsIndex({ tickets, filtros, abiertos, urgentes, tiposFalla, prioridades, isps }: Props) {
    const { auth, flash } = usePage<SharedData>().props;
    const esSuperAdmin = auth.user?.is_super_admin ?? false;
    const [search, setSearch] = useState(filtros.search ?? '');

    // --- Filtros ---
    const navegar = (nuevos: Filtros) => navegarConFiltros('/tickets', { ...nuevos });
    const buscar = (e: FormEvent) => {
        e.preventDefault();
        navegar({ ...filtros, search });
    };
    const filtrar = (clave: keyof Filtros, valor: string) => navegar({ ...filtros, [clave]: valor === TODOS && clave !== 'estado' ? undefined : valor });

    const hayFiltros = filtros.search || filtros.prioridad || filtros.tipo || filtros.isp_id || (filtros.estado && filtros.estado !== 'abierto');

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tickets de soporte" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {flash.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}
                {flash.error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                        {flash.error}
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Tickets de soporte</h1>
                        <p className="text-muted-foreground text-sm">
                            Cada ticket guarda su historial completo: quién, cuándo y qué se hizo. Para abrir uno, ve a Clientes y usa las
                            opciones del servicio.
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:max-w-xl">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Abiertos</CardTitle>
                            <LifeBuoy className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{abiertos}</p>
                        </CardContent>
                    </Card>
                    <Card className={urgentes > 0 ? 'border-red-300 dark:border-red-900' : ''}>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Urgentes abiertos</CardTitle>
                            <AlertTriangle className={`size-4 ${urgentes > 0 ? 'text-red-500' : 'text-muted-foreground'}`} />
                        </CardHeader>
                        <CardContent>
                            <p className={`text-3xl font-bold ${urgentes > 0 ? 'text-red-600 dark:text-red-400' : ''}`}>{urgentes}</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={buscar} className="flex gap-2">
                        <Input
                            placeholder="Buscar por #, código de servicio, cédula, nombre o descripción..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-96 max-w-full"
                        />
                        <Button type="submit" variant="secondary">Buscar</Button>
                    </form>

                    <Select value={filtros.estado ?? 'abierto'} onValueChange={(v) => filtrar('estado', v)}>
                        <SelectTrigger className="w-40"><SelectValue /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="abierto">Abiertos</SelectItem>
                            <SelectItem value="cerrado">Cerrados</SelectItem>
                            <SelectItem value={TODOS}>Todos</SelectItem>
                        </SelectContent>
                    </Select>

                    <Select value={filtros.prioridad ?? TODOS} onValueChange={(v) => filtrar('prioridad', v)}>
                        <SelectTrigger className="w-44"><SelectValue placeholder="Prioridad" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODOS}>Todas las prioridades</SelectItem>
                            {prioridades.map((p) => (
                                <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select value={filtros.tipo ?? TODOS} onValueChange={(v) => filtrar('tipo', v)}>
                        <SelectTrigger className="w-48"><SelectValue placeholder="Tipo de falla" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODOS}>Todos los tipos</SelectItem>
                            {tiposFalla
                                .filter((t) => esSuperAdmin || t.isp_id === auth.user?.isp_id)
                                .map((t) => (
                                    <SelectItem key={t.id} value={t.hashid ?? String(t.id)}>{t.nombre}</SelectItem>
                                ))}
                        </SelectContent>
                    </Select>

                    {esSuperAdmin && isps && (
                        <Select value={filtros.isp_id ?? TODOS} onValueChange={(v) => filtrar('isp_id', v)}>
                            <SelectTrigger className="w-48"><SelectValue placeholder="ISP" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>Todas las ISP</SelectItem>
                                {isps.map((i) => (
                                    <SelectItem key={i.id} value={i.hashid ?? ''}>{i.nombre}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    {hayFiltros && (
                        <Button type="button" variant="ghost" onClick={() => { setSearch(''); navegar({}); }}>
                            Limpiar filtros
                        </Button>
                    )}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-16">#</TableHead>
                                <TableHead>Servicio</TableHead>
                                <TableHead>Titular</TableHead>
                                {esSuperAdmin && <TableHead>ISP</TableHead>}
                                <TableHead>Tipo de falla</TableHead>
                                <TableHead>Prioridad</TableHead>
                                <TableHead>Visita</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead>Abierto</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tickets.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={esSuperAdmin ? 9 : 8} className="text-muted-foreground py-8 text-center">
                                        No hay tickets con este filtro.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                tickets.data.map((t) => (
                                    <TableRow key={t.id} className="cursor-pointer" onClick={() => router.visit(`/tickets/${t.hashid}`)}>
                                        <TableCell className="font-semibold tabular-nums">
                                            <Link href={`/tickets/${t.hashid}`} className="hover:underline" onClick={(e) => e.stopPropagation()}>
                                                #{t.numero}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <div className="font-medium">{t.servicio.codigo}</div>
                                            <div className="text-muted-foreground max-w-56 truncate text-xs" title={t.servicio.direccion ?? ''}>
                                                {t.servicio.direccion}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <div>{t.servicio.titular}</div>
                                            <div className="text-muted-foreground text-xs tabular-nums">{t.servicio.identificacion}</div>
                                        </TableCell>
                                        {esSuperAdmin && <TableCell>{t.isp}</TableCell>}
                                        <TableCell>{t.tipo ?? '—'}</TableCell>
                                        <TableCell><PrioridadBadge label={t.prioridad_label} color={t.prioridad_color} /></TableCell>
                                        <TableCell className="whitespace-nowrap tabular-nums">{t.fecha_visita ?? '—'}</TableCell>
                                        <TableCell><EstadoTicketBadge estado={t.estado} label={t.estado_label} /></TableCell>
                                        <TableCell className="text-muted-foreground whitespace-nowrap text-xs tabular-nums">
                                            {t.fecha}
                                            {t.creador && <div>por {t.creador}</div>}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {tickets.total} {tickets.total === 1 ? 'ticket' : 'tickets'}
                    </p>
                    <Pagination links={tickets.links} />
                </div>
            </div>

        </AppLayout>
    );
}
