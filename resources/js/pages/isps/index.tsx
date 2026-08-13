import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { navegarConFiltros } from '@/lib/filtros';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Paginated, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'ISPs', href: '/isps' }];

interface IspRow {
    id: number;
    hashid: string;
    nombre: string;
    tipo: string;
    tipo_label: string;
    activo: boolean;
    id_producto: number | null;
    clientes_count: number;
    users_count: number;
    es_principal: boolean;
}

interface Props {
    isps: Paginated<IspRow>;
    filtros: { search?: string };
}

export default function IspsIndex({ isps, filtros }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [search, setSearch] = useState(filtros.search ?? '');

    const [open, setOpen] = useState(false);
    const [editando, setEditando] = useState<IspRow | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({ nombre: '', activo: true, id_producto: '' });

    const abrirCrear = () => {
        reset();
        clearErrors();
        setEditando(null);
        setOpen(true);
    };

    const abrirEditar = (isp: IspRow) => {
        clearErrors();
        setData({ nombre: isp.nombre, activo: isp.activo, id_producto: isp.id_producto != null ? String(isp.id_producto) : '' });
        setEditando(isp);
        setOpen(true);
    };

    const guardar: FormEventHandler = (e) => {
        e.preventDefault();
        const opciones = { onSuccess: () => { setOpen(false); reset(); setEditando(null); } };
        if (editando) put(`/isps/${editando.hashid}`, opciones);
        else post('/isps', opciones);
    };

    const buscar = (e: FormEvent) => {
        e.preventDefault();
        navegarConFiltros('/isps', { search });
    };

    const eliminar = (isp: IspRow) => {
        if (confirm(`¿Eliminar el ISP "${isp.nombre}"?`)) {
            router.delete(`/isps/${isp.hashid}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="ISPs" />

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
                        <h1 className="text-xl font-semibold">ISPs</h1>
                        <p className="text-muted-foreground text-sm">Administra los ISP de la plataforma.</p>
                    </div>
                    <Button onClick={abrirCrear}><Plus className="size-4" /> Nuevo ISP</Button>
                </div>

                <form onSubmit={buscar} className="flex gap-2">
                    <Input placeholder="Buscar por nombre..." value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-sm" />
                    <Button type="submit" variant="secondary">Buscar</Button>
                    {filtros.search && (
                        <Button type="button" variant="ghost" onClick={() => { setSearch(''); navegarConFiltros('/isps', {}); }}>Limpiar</Button>
                    )}
                </form>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Tipo</TableHead>
                                <TableHead>Clientes</TableHead>
                                <TableHead>Usuarios</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-32 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {isps.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={6} className="text-muted-foreground py-8 text-center">No hay ISPs registrados.</TableCell>
                                </TableRow>
                            ) : (
                                isps.data.map((isp) => (
                                    <TableRow key={isp.id}>
                                        <TableCell className="font-medium">{isp.nombre}</TableCell>
                                        <TableCell>
                                            <Badge variant={isp.es_principal ? 'default' : 'secondary'}>{isp.tipo_label}</Badge>
                                        </TableCell>
                                        <TableCell>{isp.clientes_count}</TableCell>
                                        <TableCell>{isp.users_count}</TableCell>
                                        <TableCell>
                                            <span className="flex items-center gap-2">
                                                <span className="size-2.5 rounded-full" style={{ backgroundColor: isp.activo ? '#22c55e' : '#ef4444' }} />
                                                {isp.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" onClick={() => abrirEditar(isp)}><Pencil className="size-4" /></Button>
                                                {! isp.es_principal && (
                                                    <Button variant="ghost" size="icon" onClick={() => eliminar(isp)}><Trash2 className="text-destructive size-4" /></Button>
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
                        {isps.total} {isps.total === 1 ? 'ISP' : 'ISPs'} en total
                    </p>
                    <Pagination links={isps.links} />
                </div>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar ISP' : 'Nuevo ISP'}</DialogTitle>
                        <DialogDescription>
                            {editando ? 'Modifica los datos del ISP.' : 'Se creará como ISP Cliente, con sus roles y estados por defecto.'}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={guardar} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="nombre">Nombre</Label>
                            <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} autoFocus placeholder="Ej. Nube Net" />
                            <InputError message={errors.nombre} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="id_producto">ID de producto (facturación)</Label>
                            <Input id="id_producto" type="number" min={0} value={data.id_producto} onChange={(e) => setData('id_producto', e.target.value)} placeholder="Ej. 21" />
                            <InputError message={errors.id_producto} />
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox id="activo" checked={data.activo} onCheckedChange={(c) => setData('activo', c === true)} />
                            <Label htmlFor="activo">ISP activo</Label>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setOpen(false)}>Cancelar</Button>
                            <Button type="submit" disabled={processing}>{editando ? 'Guardar cambios' : 'Guardar'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
