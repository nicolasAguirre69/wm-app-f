import InputError from '@/components/input-error';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { navegarConFiltros } from '@/lib/filtros';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type OpcionSelect, type Paginated, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Usuarios', href: '/usuarios' }];
const TODOS = 'todos';

interface UsuarioRow {
    id: number;
    hashid: string;
    name: string;
    email: string;
    isp_id: number;
    isp_nombre: string | null;
    activo: boolean;
    rol: string;
}

interface Props {
    usuarios: Paginated<UsuarioRow>;
    filtros: { search?: string; isp_id?: string };
    esSuperAdmin: boolean;
    isps: OpcionSelect[] | null;
    roles: string[];
}

export default function UsuariosIndex({ usuarios, filtros, esSuperAdmin, isps, roles }: Props) {
    const { flash } = usePage<SharedData>().props;
    const [search, setSearch] = useState(filtros.search ?? '');

    const [open, setOpen] = useState(false);
    const [editando, setEditando] = useState<UsuarioRow | null>(null);
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '', email: '', password: '', isp_id: '', rol: '', activo: true,
    });

    const abrirCrear = () => {
        reset();
        clearErrors();
        setEditando(null);
        setOpen(true);
    };

    const abrirEditar = (u: UsuarioRow) => {
        clearErrors();
        setData({ name: u.name, email: u.email, password: '', isp_id: String(u.isp_id), rol: u.rol, activo: u.activo });
        setEditando(u);
        setOpen(true);
    };

    const guardar: FormEventHandler = (e) => {
        e.preventDefault();
        const opciones = { onSuccess: () => { setOpen(false); reset(); setEditando(null); } };
        if (editando) put(`/usuarios/${editando.hashid}`, opciones);
        else post('/usuarios', opciones);
    };

    const buscar = (e: FormEvent) => {
        e.preventDefault();
        navegarConFiltros('/usuarios', { ...filtros, search });
    };

    const filtrarIsp = (valor: string) => {
        navegarConFiltros('/usuarios', { ...filtros, isp_id: valor === TODOS ? undefined : valor });
    };

    const eliminar = (u: UsuarioRow) => {
        if (confirm(`¿Eliminar al usuario "${u.name}"?`)) {
            router.delete(`/usuarios/${u.hashid}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usuarios" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {flash.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Usuarios</h1>
                        <p className="text-muted-foreground text-sm">Administra los usuarios y sus roles.</p>
                    </div>
                    <Button onClick={abrirCrear}><Plus className="size-4" /> Nuevo usuario</Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={buscar} className="flex gap-2">
                        <Input placeholder="Buscar por nombre o correo..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-72 max-w-full" />
                        <Button type="submit" variant="secondary">Buscar</Button>
                    </form>
                    {esSuperAdmin && isps && (
                        <Select value={filtros.isp_id ?? TODOS} onValueChange={filtrarIsp}>
                            <SelectTrigger className="w-48"><SelectValue placeholder="ISP" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>Todas las ISP</SelectItem>
                                {isps.map((isp) => (<SelectItem key={isp.id} value={isp.hashid ?? ''}>{isp.nombre}</SelectItem>))}
                            </SelectContent>
                        </Select>
                    )}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nombre</TableHead>
                                <TableHead>Correo</TableHead>
                                {esSuperAdmin && <TableHead>ISP</TableHead>}
                                <TableHead>Rol</TableHead>
                                <TableHead>Estado</TableHead>
                                <TableHead className="w-32 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {usuarios.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={esSuperAdmin ? 6 : 5} className="text-muted-foreground py-8 text-center">No hay usuarios registrados.</TableCell>
                                </TableRow>
                            ) : (
                                usuarios.data.map((u) => (
                                    <TableRow key={u.id}>
                                        <TableCell className="font-medium">{u.name}</TableCell>
                                        <TableCell>{u.email}</TableCell>
                                        {esSuperAdmin && <TableCell>{u.isp_nombre ?? '—'}</TableCell>}
                                        <TableCell><Badge variant="secondary">{u.rol}</Badge></TableCell>
                                        <TableCell>
                                            <span className="flex items-center gap-2">
                                                <span className="size-2.5 rounded-full" style={{ backgroundColor: u.activo ? '#22c55e' : '#ef4444' }} />
                                                {u.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" onClick={() => abrirEditar(u)}><Pencil className="size-4" /></Button>
                                                <Button variant="ghost" size="icon" onClick={() => eliminar(u)}><Trash2 className="text-destructive size-4" /></Button>
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
                        {usuarios.total} {usuarios.total === 1 ? 'usuario' : 'usuarios'} en total
                    </p>
                    <Pagination links={usuarios.links} />
                </div>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar usuario' : 'Nuevo usuario'}</DialogTitle>
                        <DialogDescription>Datos de acceso y rol del usuario.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={guardar} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} autoFocus />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo</Label>
                            <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="password">{editando ? 'Contraseña (dejar vacío para no cambiar)' : 'Contraseña'}</Label>
                            <Input id="password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} placeholder="••••••••" />
                            <InputError message={errors.password} />
                        </div>

                        {/* ISP: solo el Super Admin lo elige, y solo al crear. */}
                        {esSuperAdmin && ! editando && (
                            <div className="grid gap-2">
                                <Label htmlFor="isp_id">ISP</Label>
                                <Select value={data.isp_id} onValueChange={(v) => setData('isp_id', v)}>
                                    <SelectTrigger id="isp_id"><SelectValue placeholder="Selecciona un ISP" /></SelectTrigger>
                                    <SelectContent>
                                        {(isps ?? []).map((isp) => (<SelectItem key={isp.id} value={String(isp.id)}>{isp.nombre}</SelectItem>))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.isp_id} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="rol">Rol</Label>
                            <Select value={data.rol} onValueChange={(v) => setData('rol', v)}>
                                <SelectTrigger id="rol"><SelectValue placeholder="Selecciona un rol" /></SelectTrigger>
                                <SelectContent>
                                    {roles.map((r) => (<SelectItem key={r} value={r}>{r}</SelectItem>))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.rol} />
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox id="activo" checked={data.activo} onCheckedChange={(c) => setData('activo', c === true)} />
                            <Label htmlFor="activo">Usuario activo</Label>
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
