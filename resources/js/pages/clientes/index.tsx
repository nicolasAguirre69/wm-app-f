import { ClienteFormFields, type ClienteFormValues } from '@/components/cliente-form-fields';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import { clienteSchema } from '@/lib/validations/cliente';
import AppLayout from '@/layouts/app-layout';
import { type BarrioSelect, type BreadcrumbItem, type Cliente, type Comentario, type EnumOption, type OpcionIsp, type OpcionSelect, type Paginated, type SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ArrowUpDown, MessageSquare, Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEvent, FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clientes', href: '/clientes' }];
const TODOS = 'todos';

interface Filtros {
    search?: string;
    sort?: string;
    direction?: string;
    isp_id?: string;
    facturable?: string;
}

interface Props {
    clientes: Paginated<Cliente>;
    filtros: Filtros;
    isps: OpcionSelect[] | null;
    ciudades: OpcionSelect[];
    barrios: BarrioSelect[];
    planes: OpcionIsp[];
    estados: OpcionIsp[];
    tiposIdentificacion: EnumOption[];
    tiposContribuyente: EnumOption[];
    comentarios: Comentario[];
    puedeFacturacion: boolean;
}

const vacio = (): ClienteFormValues & { _method: string } => ({
    _method: '',
    codigo_cliente: '', tipo_identificacion: '', identificacion: '', tipo_contribuyente: '',
    primer_nombre: '', segundo_nombre: '', primer_apellido: '', segundo_apellido: '',
    telefono_1: '', telefono_2: '', correo: '',
    ciudad_id: '', barrio_id: '', direccion: '',
    plan_id: '', estado_id: '', fecha_instalacion: '', dia_corte: '',
    documento_digitalizado: null,
});

export default function ClientesIndex({ clientes, filtros, isps, ciudades, barrios, planes, estados, tiposIdentificacion, tiposContribuyente, comentarios, puedeFacturacion }: Props) {
    const { can } = usePermissions();
    const { auth, flash } = usePage<SharedData>().props;
    const esSuperAdmin = auth.user?.is_super_admin ?? false;
    const [search, setSearch] = useState(filtros.search ?? '');

    // --- Comentarios ---
    const [comentariosOpen, setComentariosOpen] = useState(false);
    const [comentariosCliente, setComentariosCliente] = useState<Cliente | null>(null);
    const [tabComentario, setTabComentario] = useState<'seguimiento' | 'facturacion'>('seguimiento');
    const [nuevoComentario, setNuevoComentario] = useState('');
    const [enviandoComentario, setEnviandoComentario] = useState(false);
    const [editandoId, setEditandoId] = useState<number | null>(null);
    const [editContenido, setEditContenido] = useState('');

    const abrirComentarios = (cliente: Cliente) => {
        setComentariosCliente(cliente);
        setTabComentario('seguimiento');
        setNuevoComentario('');
        setComentariosOpen(true);
        // Carga bajo demanda: solo trae la prop 'comentarios' de ese cliente.
        router.get('/clientes', { ...filtros, comentarios_de: cliente.id }, { only: ['comentarios'], preserveState: true, preserveScroll: true });
    };

    const agregarComentario = (e: FormEvent) => {
        e.preventDefault();
        if (! comentariosCliente || ! nuevoComentario.trim()) return;
        setEnviandoComentario(true);
        router.post(
            `/clientes/${comentariosCliente.id}/comentarios`,
            { tipo: tabComentario, contenido: nuevoComentario },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => setNuevoComentario(''),
                onFinish: () => setEnviandoComentario(false),
            },
        );
    };

    const borrarComentario = (id: number) => {
        router.delete(`/comentarios/${id}`, { preserveState: true, preserveScroll: true });
    };

    const iniciarEdicion = (c: Comentario) => {
        setEditandoId(c.id);
        setEditContenido(c.contenido);
    };

    const guardarEdicion = (id: number) => {
        if (! editContenido.trim()) return;
        router.put(`/comentarios/${id}`, { contenido: editContenido }, {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => setEditandoId(null),
        });
    };

    // Comentarios del tab activo.
    const comentariosVisibles = comentarios.filter((c) => c.tipo === tabComentario);

    const [open, setOpen] = useState(false);
    const [editando, setEditando] = useState<Cliente | null>(null);
    const [docActual, setDocActual] = useState<string | null>(null);
    const [clientErrors, setClientErrors] = useState<Record<string, string>>({});
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<ClienteFormValues & { _method: string }>(vacio());

    const abrirCrear = () => {
        reset();
        setData(vacio());
        clearErrors();
        setClientErrors({});
        setDocActual(null);
        setEditando(null);
        setOpen(true);
    };

    const abrirEditar = (c: Cliente) => {
        clearErrors();
        setClientErrors({});
        setData({
            _method: 'put',
            codigo_cliente: c.codigo_cliente,
            tipo_identificacion: c.tipo_identificacion,
            identificacion: c.identificacion,
            tipo_contribuyente: c.tipo_contribuyente,
            primer_nombre: c.primer_nombre,
            segundo_nombre: c.segundo_nombre ?? '',
            primer_apellido: c.primer_apellido,
            segundo_apellido: c.segundo_apellido ?? '',
            telefono_1: c.telefono_1,
            telefono_2: c.telefono_2 ?? '',
            correo: c.correo ?? '',
            ciudad_id: String(c.ciudad_id),
            barrio_id: String(c.barrio_id),
            direccion: c.direccion,
            plan_id: String(c.plan_id),
            estado_id: String(c.estado_id),
            // Campos que pueden venir nulos (clientes importados).
            fecha_instalacion: c.fecha_instalacion ? c.fecha_instalacion.slice(0, 10) : '',
            dia_corte: c.dia_corte != null ? String(c.dia_corte) : '',
            documento_digitalizado: null,
        });
        setDocActual(c.documento_digitalizado);
        setEditando(c);
        setOpen(true);
    };

    const guardar: FormEventHandler = (e) => {
        e.preventDefault();
        const result = clienteSchema(!editando).safeParse(data);
        if (!result.success) {
            const errs: Record<string, string> = {};
            result.error.issues.forEach((i) => { errs[String(i.path[0])] = i.message; });
            setClientErrors(errs);
            return;
        }
        setClientErrors({});
        const opciones = { forceFormData: true, onSuccess: () => { setOpen(false); reset(); setEditando(null); } };
        post(editando ? `/clientes/${editando.id}` : '/clientes', opciones);
    };

    const buscar = (e: FormEvent) => {
        e.preventDefault();
        router.get('/clientes', { ...filtros, search }, { preserveState: true, replace: true });
    };

    const filtrar = (clave: 'isp_id' | 'facturable', valor: string) => {
        router.get('/clientes', { ...filtros, [clave]: valor === TODOS ? undefined : valor }, { preserveState: true, replace: true });
    };

    const ordenarPor = (columna: string) => {
        const direction = filtros.sort === columna && filtros.direction === 'asc' ? 'desc' : 'asc';
        router.get('/clientes', { ...filtros, sort: columna, direction }, { preserveState: true, replace: true });
    };

    const iconoOrden = (columna: string) => {
        if (filtros.sort !== columna) return <ArrowUpDown className="ml-1 inline size-3.5 opacity-50" />;
        return filtros.direction === 'asc' ? <ArrowUp className="ml-1 inline size-3.5" /> : <ArrowDown className="ml-1 inline size-3.5" />;
    };

    const eliminar = (c: Cliente) => {
        if (confirm(`¿Eliminar el cliente "${c.codigo_cliente}"?`)) {
            router.delete(`/clientes/${c.id}`, { preserveScroll: true });
        }
    };

    const toggleFacturable = (c: Cliente) => {
        if (c.facturable) {
            const motivo = prompt('Motivo para marcar como NO facturable:');
            if (motivo === null) return;
            router.patch(`/clientes/${c.id}/facturable`, { facturable: false, motivo_no_facturable: motivo }, { preserveScroll: true });
        } else {
            router.patch(`/clientes/${c.id}/facturable`, { facturable: true }, { preserveScroll: true });
        }
    };

    const nombreCompleto = (c: Cliente) => [c.primer_nombre, c.segundo_nombre, c.primer_apellido, c.segundo_apellido].filter(Boolean).join(' ');
    const mergedErrors = { ...errors, ...clientErrors };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clientes" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {flash.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-xl font-semibold">Clientes</h1>
                        <p className="text-muted-foreground text-sm">Administra los clientes de tu ISP.</p>
                    </div>
                    {can('clientes.crear') && (
                        <Button onClick={abrirCrear}><Plus className="size-4" /> Nuevo cliente</Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={buscar} className="flex gap-2">
                        <Input placeholder="Buscar por código, identificación, nombre o correo..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-80 max-w-full" />
                        <Button type="submit" variant="secondary">Buscar</Button>
                    </form>

                    {esSuperAdmin && isps && (
                        <Select value={filtros.isp_id ?? TODOS} onValueChange={(v) => filtrar('isp_id', v)}>
                            <SelectTrigger className="w-48"><SelectValue placeholder="ISP" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>Todas las ISP</SelectItem>
                                {isps.map((isp) => (<SelectItem key={isp.id} value={String(isp.id)}>{isp.nombre}</SelectItem>))}
                            </SelectContent>
                        </Select>
                    )}

                    {/* Filtro de facturable: EXCLUSIVO del Super Admin. */}
                    {esSuperAdmin && (
                        <Select value={filtros.facturable ?? TODOS} onValueChange={(v) => filtrar('facturable', v)}>
                            <SelectTrigger className="w-44"><SelectValue placeholder="Facturable" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>Todos</SelectItem>
                                <SelectItem value="1">Facturables</SelectItem>
                                <SelectItem value="0">No facturables</SelectItem>
                            </SelectContent>
                        </Select>
                    )}

                    {(filtros.search || filtros.isp_id || filtros.facturable) && (
                        <Button type="button" variant="ghost" onClick={() => { setSearch(''); router.get('/clientes', {}, { preserveState: true, replace: true }); }}>Limpiar filtros</Button>
                    )}
                </div>

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>
                                    <button type="button" onClick={() => ordenarPor('codigo_cliente')} className="flex items-center font-medium">
                                        Código {iconoOrden('codigo_cliente')}
                                    </button>
                                </TableHead>
                                <TableHead>Nombre</TableHead>
                                {esSuperAdmin && <TableHead>ISP</TableHead>}
                                <TableHead>Identificación</TableHead>
                                <TableHead>Servicio</TableHead>
                                <TableHead>Estado</TableHead>
                                {esSuperAdmin && <TableHead>Facturable</TableHead>}
                                <TableHead className="w-32 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {clientes.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={esSuperAdmin ? 8 : 6} className="text-muted-foreground py-8 text-center">No hay clientes registrados.</TableCell>
                                </TableRow>
                            ) : (
                                clientes.data.map((cliente) => (
                                    <TableRow key={cliente.id} style={cliente.estado?.color ? { backgroundColor: `${cliente.estado.color}22` } : undefined}>
                                        <TableCell className="font-medium">{cliente.codigo_cliente}</TableCell>
                                        <TableCell>{nombreCompleto(cliente)}</TableCell>
                                        {esSuperAdmin && <TableCell>{cliente.isp?.nombre ?? '—'}</TableCell>}
                                        <TableCell>{cliente.identificacion}</TableCell>
                                        <TableCell>{cliente.plan?.tipo_servicio?.nombre ?? '—'}</TableCell>
                                        <TableCell>
                                            <span className="flex items-center gap-2">
                                                <span className="size-2.5 rounded-full border" style={{ backgroundColor: cliente.estado?.color ?? '#e5e7eb' }} />
                                                {cliente.estado?.nombre ?? '—'}
                                            </span>
                                        </TableCell>
                                        {esSuperAdmin && (
                                            <TableCell>
                                                <button type="button" onClick={() => toggleFacturable(cliente)}
                                                    title={`${cliente.facturable ? 'Facturable' : 'No facturable'} — clic para cambiar`}
                                                    className="inline-block size-3.5 rounded-full ring-offset-2 transition hover:ring-2 hover:ring-foreground/40"
                                                    style={{ backgroundColor: cliente.facturable ? '#22c55e' : '#ef4444' }} />
                                            </TableCell>
                                        )}
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="icon" title="Comentarios" onClick={() => abrirComentarios(cliente)}>
                                                    <MessageSquare className="size-4" />
                                                </Button>
                                                {can('clientes.editar') && (
                                                    <Button variant="ghost" size="icon" onClick={() => abrirEditar(cliente)}><Pencil className="size-4" /></Button>
                                                )}
                                                {can('clientes.eliminar') && (
                                                    <Button variant="ghost" size="icon" onClick={() => eliminar(cliente)}><Trash2 className="text-destructive size-4" /></Button>
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
                        {clientes.total} {clientes.total === 1 ? 'cliente' : 'clientes'} en total
                    </p>
                    <Pagination links={clientes.links} />
                </div>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar cliente' : 'Nuevo cliente'}</DialogTitle>
                        <DialogDescription>Datos del cliente.</DialogDescription>
                    </DialogHeader>

                    <form onSubmit={guardar} className="space-y-6">
                        <ClienteFormFields
                            data={data}
                            setData={setData}
                            errors={mergedErrors}
                            ciudades={ciudades}
                            barrios={barrios}
                            planes={planes}
                            estados={estados}
                            tiposIdentificacion={tiposIdentificacion}
                            tiposContribuyente={tiposContribuyente}
                            documentoActual={docActual}
                            ispId={editando ? editando.isp_id : esSuperAdmin ? null : (auth.user?.isp_id ?? null)}
                        />
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setOpen(false)}>Cancelar</Button>
                            <Button type="submit" disabled={processing}>{editando ? 'Guardar cambios' : 'Guardar cliente'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal de comentarios */}
            <Dialog open={comentariosOpen} onOpenChange={setComentariosOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Comentarios — {comentariosCliente?.codigo_cliente}</DialogTitle>
                        <DialogDescription>Seguimiento y observaciones del cliente.</DialogDescription>
                    </DialogHeader>

                    {/* Pestañas */}
                    <div className="flex gap-2 border-b">
                        <button
                            type="button"
                            onClick={() => setTabComentario('seguimiento')}
                            className={`-mb-px border-b-2 px-3 py-2 text-sm ${tabComentario === 'seguimiento' ? 'border-primary font-medium' : 'border-transparent text-muted-foreground'}`}
                        >
                            Seguimiento
                        </button>
                        {puedeFacturacion && (
                            <button
                                type="button"
                                onClick={() => setTabComentario('facturacion')}
                                className={`-mb-px border-b-2 px-3 py-2 text-sm ${tabComentario === 'facturacion' ? 'border-primary font-medium' : 'border-transparent text-muted-foreground'}`}
                            >
                                Facturación
                            </button>
                        )}
                    </div>

                    {/* Lista de comentarios */}
                    <div className="max-h-72 space-y-3 overflow-y-auto">
                        {comentariosVisibles.length === 0 ? (
                            <p className="text-muted-foreground py-4 text-center text-sm">No hay comentarios de {tabComentario}.</p>
                        ) : (
                            comentariosVisibles.map((c) => (
                                <div key={c.id} className="rounded-lg border p-3">
                                    {editandoId === c.id ? (
                                        <div className="space-y-2">
                                            <textarea
                                                value={editContenido}
                                                onChange={(e) => setEditContenido(e.target.value)}
                                                rows={3}
                                                className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                                            />
                                            <div className="flex justify-end gap-2">
                                                <Button type="button" size="sm" variant="ghost" onClick={() => setEditandoId(null)}>Cancelar</Button>
                                                <Button type="button" size="sm" onClick={() => guardarEdicion(c.id)}>Guardar</Button>
                                            </div>
                                        </div>
                                    ) : (
                                        <>
                                            <p className="text-sm whitespace-pre-wrap">{c.contenido}</p>
                                            <div className="text-muted-foreground mt-2 flex items-center justify-between text-xs">
                                                <span>{c.autor ?? 'Usuario'} · {c.fecha}</span>
                                                {c.puede_borrar && (
                                                    <div className="flex gap-3">
                                                        <button type="button" onClick={() => iniciarEdicion(c)} className="hover:underline">
                                                            Editar
                                                        </button>
                                                        <button type="button" onClick={() => borrarComentario(c.id)} className="text-destructive hover:underline">
                                                            Eliminar
                                                        </button>
                                                    </div>
                                                )}
                                            </div>
                                        </>
                                    )}
                                </div>
                            ))
                        )}
                    </div>

                    {/* Agregar comentario */}
                    <form onSubmit={agregarComentario} className="space-y-2">
                        <textarea
                            value={nuevoComentario}
                            onChange={(e) => setNuevoComentario(e.target.value)}
                            placeholder={`Escribe un comentario de ${tabComentario}...`}
                            rows={3}
                            className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <div className="flex justify-end">
                            <Button type="submit" disabled={enviandoComentario || ! nuevoComentario.trim()}>Agregar</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
