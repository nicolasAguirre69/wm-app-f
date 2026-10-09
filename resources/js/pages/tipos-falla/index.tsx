import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tickets', href: '/tickets' },
    { title: 'Tipos de falla', href: '/tipos-falla' },
];

interface TipoFalla {
    id: number;
    hashid: string;
    nombre: string;
    activo: boolean;
    tickets: number;
}

interface Props {
    tipos: TipoFalla[];
}

export default function TiposFallaIndex({ tipos }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const [editando, setEditando] = useState<TipoFalla | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({ nombre: '', activo: true });

    const abrirCrear = () => {
        reset();
        clearErrors();
        setEditando(null);
        setOpen(true);
    };

    const abrirEditar = (tipo: TipoFalla) => {
        clearErrors();
        setData({ nombre: tipo.nombre, activo: tipo.activo });
        setEditando(tipo);
        setOpen(true);
    };

    const guardar: FormEventHandler = (e) => {
        e.preventDefault();
        const opciones = { preserveScroll: true, onSuccess: () => { setOpen(false); reset(); setEditando(null); } };
        if (editando) put(`/tipos-falla/${editando.hashid}`, opciones);
        else post('/tipos-falla', opciones);
    };

    const alternarActivo = (tipo: TipoFalla) => {
        router.put(`/tipos-falla/${tipo.hashid}`, { nombre: tipo.nombre, activo: !tipo.activo }, { preserveScroll: true });
    };

    const eliminar = (tipo: TipoFalla) => {
        if (confirm(`¿Eliminar el tipo de falla "${tipo.nombre}"?`)) {
            router.delete(`/tipos-falla/${tipo.hashid}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tipos de falla" />

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
                        <h1 className="text-xl font-semibold">Tipos de falla</h1>
                        <p className="text-muted-foreground text-sm">
                            Categorías para clasificar los tickets. Los inactivos no aparecen al crear tickets, pero se conservan en el historial.
                        </p>
                    </div>
                    <Button onClick={abrirCrear}><Plus className="size-4" /> Nuevo tipo</Button>
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead className="w-28 text-center">Activo</TableHead>
                                <TableHead className="w-28 text-right">Tickets</TableHead>
                                <TableHead className="w-28 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {tipos.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={4} className="text-muted-foreground py-8 text-center">No hay tipos de falla registrados.</TableCell>
                                </TableRow>
                            ) : (
                                tipos.map((tipo) => (
                                    <TableRow key={tipo.id} className={tipo.activo ? '' : 'opacity-60'}>
                                        <TableCell className="font-medium">{tipo.nombre}</TableCell>
                                        <TableCell className="text-center">
                                            <Checkbox checked={tipo.activo} onCheckedChange={() => alternarActivo(tipo)} aria-label="Activo" />
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">{tipo.tickets}</TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" onClick={() => abrirEditar(tipo)}><Pencil className="size-4" /></Button>
                                                {tipo.tickets === 0 && (
                                                    <Button variant="ghost" size="icon" onClick={() => eliminar(tipo)}><Trash2 className="text-destructive size-4" /></Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar tipo de falla' : 'Nuevo tipo de falla'}</DialogTitle>
                        <DialogDescription>Se usa para clasificar los tickets de soporte.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={guardar} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="nombre">Nombre</Label>
                            <Input id="nombre" value={data.nombre} maxLength={60} onChange={(e) => setData('nombre', e.target.value)} autoFocus placeholder="Ej. Fibra cortada" />
                            <InputError message={errors.nombre} />
                        </div>

                        {editando && (
                            <div className="flex items-center gap-2">
                                <Checkbox id="activo" checked={data.activo} onCheckedChange={(c) => setData('activo', c === true)} />
                                <Label htmlFor="activo" className="text-sm font-normal">Activo (disponible al crear tickets)</Label>
                            </div>
                        )}

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
