import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { type BarrioOption, type BreadcrumbItem, type Paginated, type Red, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Redes', href: '/redes' }];
const NUMEROS = Array.from({ length: 16 }, (_, i) => i + 1);

interface Filtros {
    search?: string;
    sort?: string;
    direction?: string;
}

interface Props {
    redes: Paginated<Red>;
    filtros: Filtros;
    barrios: BarrioOption[];
}

export default function RedesIndex({ redes, filtros, barrios }: Props) {
    const { can } = usePermissions();
    const { flash } = usePage<SharedData>().props;
    const [search, setSearch] = useState(filtros.search ?? '');

    const [open, setOpen] = useState(false);
    const [editando, setEditando] = useState<Red | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({ barrio_id: '', numero: '' });

    const abrirCrear = () => { reset(); clearErrors(); setEditando(null); setOpen(true); };
    const abrirEditar = (red: Red) => {
        clearErrors();
        setData({ barrio_id: String(red.barrio_id), numero: String(red.numero) });
        setEditando(red);
        setOpen(true);
    };

    const guardar: FormEventHandler = (e) => {
        e.preventDefault();
        const opciones = { onSuccess: () => { setOpen(false); reset(); setEditando(null); } };
        if (editando) put(`/redes/${editando.id}`, opciones);
        else post('/redes', opciones);
    };

    const barrioSel = barrios.find((b) => String(b.id) === data.barrio_id);
    const preview = data.numero && barrioSel ? `${data.numero}${barrioSel.prefijo}` : null;

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
        return filtros.direction === 'asc' ? <ArrowUp className="ml-1 inline size-3.5" /> : <ArrowDown className="ml-1 inline size-3.5" />;
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
                        <Button onClick={abrirCrear}><Plus className="size-4" /> Nueva red</Button>
                    )}
                </div>

                <form onSubmit={buscar} className="flex gap-2">
                    <Input placeholder="Buscar por nombre..." value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-sm" />
                    <Button type="submit" variant="secondary">Buscar</Button>
                    {filtros.search && (
                        <Button type="button" variant="ghost" onClick={() => { setSearch(''); router.get('/redes', {}, { preserveState: true, replace: true }); }}>Limpiar</Button>
                    )}
                </form>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    <button type="button" onClick={() => ordenarPor('numero')} className="flex items-center font-medium">
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
                                    <TableCell colSpan={4} className="text-muted-foreground py-8 text-center">No hay redes registradas.</TableCell>
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
                                                    <Button variant="ghost" size="icon" onClick={() => abrirEditar(red)}><Pencil className="size-4" /></Button>
                                                )}
                                                {can('redes.eliminar') && (
                                                    <Button variant="ghost" size="icon" onClick={() => eliminar(red)}><Trash2 className="text-destructive size-4" /></Button>
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

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar red' : 'Nueva red'}</DialogTitle>
                        <DialogDescription>Elige el barrio y el número; el prefijo se agrega solo.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={guardar} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="barrio_id">Barrio</Label>
                            <Select value={data.barrio_id} onValueChange={(v) => setData('barrio_id', v)}>
                                <SelectTrigger id="barrio_id"><SelectValue placeholder="Selecciona un barrio" /></SelectTrigger>
                                <SelectContent>
                                    {barrios.map((b) => (<SelectItem key={b.id} value={String(b.id)}>{b.nombre}</SelectItem>))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.barrio_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="numero">Número (1-16)</Label>
                            <Select value={data.numero} onValueChange={(v) => setData('numero', v)}>
                                <SelectTrigger id="numero"><SelectValue placeholder="Selecciona un número" /></SelectTrigger>
                                <SelectContent>
                                    {NUMEROS.map((n) => (<SelectItem key={n} value={String(n)}>{n}</SelectItem>))}
                                </SelectContent>
                            </Select>
                            {preview && (
                                <p className="text-muted-foreground text-sm">Nombre completo: <span className="text-foreground font-medium">{preview}</span></p>
                            )}
                            <InputError message={errors.numero} />
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
