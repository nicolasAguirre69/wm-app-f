import { ClienteFormFields, type ClienteFormValues, type ModoFormulario } from '@/components/cliente-form-fields';
import { NuevoTicketDialog, type PrioridadOpcion, type TipoFallaOpcion } from '@/components/nuevo-ticket-dialog';
import { PagosDialog } from '@/components/pagos-dialog';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import { navegarConFiltros } from '@/lib/filtros';
import { clienteSchema } from '@/lib/validations/cliente';
import AppLayout from '@/layouts/app-layout';
import {
    type BarrioSelect,
    type BreadcrumbItem,
    type Cliente,
    type Comentario,
    type EnumOption,
    type OpcionIsp,
    type OpcionSelect,
    type Paginated,
    type SharedData,
    type Titular,
} from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    ChevronDown,
    ChevronRight,
    Download,
    FileText,
    Filter,
    LifeBuoy,
    MessageSquare,
    MoreHorizontal,
    Pencil,
    Plus,
    Receipt,
    Trash2,
    UserCog,
} from 'lucide-react';
import { CSSProperties, FormEvent, FormEventHandler, Fragment, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Clientes', href: '/clientes' }];
const TODOS = 'todos';

interface Filtros {
    search?: string;
    sort?: string;
    direction?: string;
    isp_id?: string;
    facturable?: string;
    estado?: string;
    puerto?: string;
}

interface Props {
    titulares: Paginated<Titular>;
    totalServicios: number;
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
    estadosFiltro: string[];
    ispsPrincipales: number[];
    ispsPlanesPropios: number[];
    muestraPuerto: boolean;
    // Catálogos para crear tickets desde el servicio (null: sin permiso / ISP Solo TV).
    tickets: { tiposFalla: TipoFallaOpcion[]; prioridades: PrioridadOpcion[] } | null;
    // Pagos y comprobantes: ISP cuyos servicios los manejan (null: sin permiso).
    pagos: { isps: number[] } | null;
}

// Valores del formulario + campos de control: _method (PUT por POST, para
// poder enviar archivos) y titular (hashid al agregar un servicio a una persona).
type Formulario = ClienteFormValues & { _method: string; titular: string };

const vacio = (): Formulario => ({
    _method: '',
    titular: '',
    codigo_cliente: '', tipo_identificacion: '', identificacion: '', tipo_contribuyente: '',
    primer_nombre: '', segundo_nombre: '', primer_apellido: '', segundo_apellido: '',
    telefono_1: '', telefono_2: '', correo: '',
    ciudad_id: '', barrio_id: '', direccion: '',
    plan_id: '', estado_id: '', fecha_instalacion: '', dia_corte: '',
    puerto_alquilado: false,
    documento_digitalizado: null,
});

// Datos de la persona en el formulario (al editar el titular).
const datosTitular = (t: Titular) => ({
    tipo_identificacion: t.tipo_identificacion,
    identificacion: t.identificacion,
    tipo_contribuyente: t.tipo_contribuyente,
    primer_nombre: t.primer_nombre,
    segundo_nombre: t.segundo_nombre ?? '',
    primer_apellido: t.primer_apellido ?? '',
    segundo_apellido: t.segundo_apellido ?? '',
    telefono_1: t.telefono_1 ?? '',
    telefono_2: t.telefono_2 ?? '',
    correo: t.correo ?? '',
});

const nombres = (t: Titular) => [t.primer_nombre, t.segundo_nombre].filter(Boolean).join(' ');
const apellidos = (t: Titular) => [t.primer_apellido, t.segundo_apellido].filter(Boolean).join(' ');
const nombreCompleto = (t: Titular) => [nombres(t), apellidos(t)].filter(Boolean).join(' ');

// Formatea el valor del plan como pesos colombianos, sin decimales.
const formatearValor = (valor?: string) =>
    valor == null ? '—' : new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(valor));

// Nombre del plan con su velocidad: "Hogar - Internet + TV - 300 Mb".
// Los planes de solo TV no tienen velocidad: "Hogar - TV".
const nombrePlan = (c: Cliente) => {
    const servicio = [c.plan?.cantidad ? `${c.plan.cantidad} Mbps` : null, c.plan?.tipo_servicio?.nombre].filter(Boolean).join(' ');
    return [c.plan?.tipo_plan?.nombre, servicio].filter(Boolean).join(' - ') || '—';
};
// Documento digitalizado del servicio: se sirve desde la base, con permiso.
const urlDocumento = (c: Cliente) => `/clientes/${c.hashid}/documento`;

// Las peticiones que cambian algo conservan el estado de la página (filas
// desplegadas, scroll) al volver.
const conservar = { preserveState: true, preserveScroll: true };

export default function ClientesIndex({
    titulares,
    totalServicios,
    filtros,
    isps,
    ciudades,
    barrios,
    planes,
    estados,
    tiposIdentificacion,
    tiposContribuyente,
    comentarios,
    puedeFacturacion,
    estadosFiltro,
    ispsPrincipales,
    ispsPlanesPropios,
    muestraPuerto,
    tickets,
    pagos,
}: Props) {
    const { can } = usePermissions();
    const { auth, flash } = usePage<SharedData>().props;
    const esSuperAdmin = auth.user?.is_super_admin ?? false;
    // Tickets de soporte: se crean desde las opciones del servicio. Solo en
    // servicios cuya ISP tiene el módulo (principal y "Gestión completa").
    const [ticketDe, setTicketDe] = useState<{ hashid: string; codigo: string; titular: string; isp_id: number } | null>(null);
    const tiposFallaDe = (ispId: number) => tickets?.tiposFalla.filter((t) => t.isp_id === ispId) ?? [];

    // Pagos del servicio (registro y comprobante PDF).
    const [pagosDe, setPagosDe] = useState<{ hashid: string; codigo: string; titular: string } | null>(null);
    const manejaPagos = (ispId: number) => pagos?.isps.includes(ispId) ?? false;
    const [search, setSearch] = useState(filtros.search ?? '');

    // --- Filas desplegadas (titulares con sus servicios visibles) ---
    // Todas empiezan cerradas, también al buscar; se abren con la flecha.
    const [abiertos, setAbiertos] = useState<Set<number>>(() => new Set());

    const alternar = (id: number) =>
        setAbiertos((prev) => {
            const nuevo = new Set(prev);
            if (nuevo.has(id)) nuevo.delete(id);
            else nuevo.add(id);
            return nuevo;
        });

    // --- Comentarios (por servicio) ---
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
        // Carga bajo demanda: solo trae la prop 'comentarios' de ese servicio.
        navegar(filtros, { comentarios_de: cliente.hashid }, { only: ['comentarios'], preserveScroll: true });
    };

    const agregarComentario = (e: FormEvent) => {
        e.preventDefault();
        if (!comentariosCliente || !nuevoComentario.trim()) return;
        setEnviandoComentario(true);
        router.post(
            `/clientes/${comentariosCliente.hashid}/comentarios`,
            { tipo: tabComentario, contenido: nuevoComentario },
            {
                ...conservar,
                onSuccess: () => setNuevoComentario(''),
                onFinish: () => setEnviandoComentario(false),
            },
        );
    };

    const borrarComentario = (hashid: string) => {
        router.delete(`/comentarios/${hashid}`, conservar);
    };

    const iniciarEdicion = (c: Comentario) => {
        setEditandoId(c.id);
        setEditContenido(c.contenido);
    };

    const guardarEdicion = (hashid: string) => {
        if (!editContenido.trim()) return;
        router.put(`/comentarios/${hashid}`, { contenido: editContenido }, { ...conservar, onSuccess: () => setEditandoId(null) });
    };

    const comentariosVisibles = comentarios.filter((c) => c.tipo === tabComentario);

    // --- Formulario (un solo modal, tres modos) ---
    //   'completo' -> Nuevo cliente: persona + su primer servicio.
    //   'titular'  -> Editar los datos de la persona (aplica a todos sus servicios).
    //   'servicio' -> Agregar un servicio a una persona, o editar un servicio.
    const [open, setOpen] = useState(false);
    const [modo, setModo] = useState<ModoFormulario>('completo');
    const [titularSel, setTitularSel] = useState<Titular | null>(null);
    const [servicioSel, setServicioSel] = useState<Cliente | null>(null);
    const [docActual, setDocActual] = useState<string | null>(null);
    const [clientErrors, setClientErrors] = useState<Record<string, string>>({});
    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm<Formulario>(vacio());

    // ISP del formulario: la del titular (al editar o agregar servicio) o la del
    // usuario (cliente nuevo). En una ISP cliente el plan es TV (lo asigna el
    // sistema) y no se pide documento; en la principal el documento es
    // obligatorio al crear un servicio.
    const ispFormulario = titularSel ? titularSel.isp_id : esSuperAdmin ? null : (auth.user?.isp_id ?? null);
    const esIspCliente = ispFormulario != null && !ispsPrincipales.includes(ispFormulario);
    // ISP cliente sin "planes propios": el plan es siempre TV y no se muestra.
    const planAutomatico = ispFormulario != null && !ispsPlanesPropios.includes(ispFormulario);
    const creandoServicio = modo === 'completo' || (modo === 'servicio' && !servicioSel);

    // Estado "Activo" de la ISP, preseleccionado al crear un servicio.
    const estadoActivo = (ispId: number | null) =>
        estados.find((e) => e.nombre === 'Activo' && (ispId == null || e.isp_id === ispId));

    const prepararModal = (nuevoModo: ModoFormulario, titular: Titular | null, servicio: Cliente | null) => {
        reset();
        clearErrors();
        setClientErrors({});
        setModo(nuevoModo);
        setTitularSel(titular);
        setServicioSel(servicio);
        setDocActual(servicio?.tiene_documento ? urlDocumento(servicio) : null);
        setOpen(true);
    };

    const abrirNuevoCliente = () => {
        prepararModal('completo', null, null);
        // El cliente nuevo queda en la ISP del usuario (también para el Super Admin).
        setData({ ...vacio(), estado_id: String(estadoActivo(auth.user?.isp_id ?? null)?.id ?? '') });
    };

    const abrirEditarTitular = (t: Titular) => {
        prepararModal('titular', t, null);
        setData({ ...vacio(), ...datosTitular(t) });
    };

    const abrirAgregarServicio = (t: Titular) => {
        prepararModal('servicio', t, null);
        setData({ ...vacio(), titular: t.hashid, estado_id: String(estadoActivo(t.isp_id)?.id ?? '') });
    };

    const abrirEditarServicio = (t: Titular, c: Cliente) => {
        prepararModal('servicio', t, c);
        setData({
            ...vacio(),
            _method: 'put',
            codigo_cliente: c.codigo_cliente,
            ciudad_id: c.ciudad_id != null ? String(c.ciudad_id) : '',
            barrio_id: String(c.barrio_id),
            direccion: c.direccion,
            plan_id: String(c.plan_id),
            estado_id: String(c.estado_id),
            // Campos que pueden venir nulos (clientes importados).
            fecha_instalacion: c.fecha_instalacion ? c.fecha_instalacion.slice(0, 10) : '',
            dia_corte: c.dia_corte != null ? String(c.dia_corte) : '',
            puerto_alquilado: c.puerto_alquilado ?? false,
        });
    };

    const guardar: FormEventHandler = (e) => {
        e.preventDefault();
        const result = clienteSchema(creandoServicio, esIspCliente, modo, planAutomatico).safeParse(data);
        if (!result.success) {
            const errs: Record<string, string> = {};
            result.error.issues.forEach((i) => {
                errs[String(i.path[0])] = i.message;
            });
            setClientErrors(errs);
            return;
        }
        setClientErrors({});

        const cerrar = () => {
            setOpen(false);
            reset();
        };

        if (modo === 'titular' && titularSel) {
            put(`/titulares/${titularSel.hashid}`, { ...conservar, onSuccess: cerrar });
            return;
        }

        // Al agregar un servicio, la fila de esa persona queda desplegada.
        const alGuardar = () => {
            cerrar();
            if (titularSel) setAbiertos((prev) => new Set(prev).add(titularSel.id));
        };
        const opciones = { ...conservar, forceFormData: true, onSuccess: alGuardar };
        post(servicioSel ? `/clientes/${servicioSel.hashid}` : '/clientes', opciones);
    };

    const tituloModal =
        modo === 'completo'
            ? 'Nuevo cliente'
            : modo === 'titular'
              ? 'Editar titular'
              : servicioSel
                ? `Editar servicio ${servicioSel.codigo_cliente}`
                : 'Agregar servicio';

    const descripcionModal =
        modo === 'completo'
            ? 'Datos de la persona y de su primer servicio.'
            : modo === 'titular'
              ? 'Los cambios aplican a todos los servicios de esta persona.'
              : titularSel
                ? `Servicio a nombre de ${nombreCompleto(titularSel)} (${titularSel.identificacion}).`
                : '';

    // --- Filtros y orden ---
    // Empaqueta los filtros en el parámetro opaco ?f= (helper compartido).
    const navegar = (nuevos: Filtros, extra: Record<string, string> = {}, opts: Record<string, unknown> = {}) =>
        navegarConFiltros('/clientes', { ...nuevos }, extra, opts);

    const buscar = (e: FormEvent) => {
        e.preventDefault();
        navegar({ ...filtros, search });
    };

    const filtrar = (clave: 'isp_id' | 'facturable' | 'estado' | 'puerto', valor: string) => {
        navegar({ ...filtros, [clave]: valor === TODOS ? undefined : valor });
    };

    const ordenarPor = (columna: string) => {
        const direction = filtros.sort === columna && filtros.direction === 'asc' ? 'desc' : 'asc';
        navegar({ ...filtros, sort: columna, direction });
    };

    const iconoOrden = (columna: string) => {
        if (filtros.sort !== columna) return <ArrowUpDown className="ml-1 inline size-3.5 opacity-50" />;
        return filtros.direction === 'asc' ? <ArrowUp className="ml-1 inline size-3.5" /> : <ArrowDown className="ml-1 inline size-3.5" />;
    };

    // Cabecera clicable para ordenar por una columna.
    const thOrden = (columna: string, etiqueta: string, clase = '') => (
        <button type="button" onClick={() => ordenarPor(columna)} className={`flex items-center font-medium ${clase}`}>
            {etiqueta} {iconoOrden(columna)}
        </button>
    );

    // --- Acciones sobre un servicio ---
    const eliminar = (c: Cliente) => {
        if (confirm(`¿Eliminar el servicio "${c.codigo_cliente}"?`)) {
            router.delete(`/clientes/${c.hashid}`, conservar);
        }
    };

    const toggleFacturable = (c: Cliente) => {
        if (c.facturable) {
            const motivo = prompt('Motivo para marcar como NO facturable:');
            if (motivo === null) return;
            router.patch(`/clientes/${c.hashid}/facturable`, { facturable: false, motivo_no_facturable: motivo }, conservar);
        } else {
            router.patch(`/clientes/${c.hashid}/facturable`, { facturable: true }, conservar);
        }
    };

    // Estado al que pasa con un clic: Activo <-> Corte en la principal y en las
    // ISP con planes propios; Activo <-> Retirado en las ISP cliente solo TV.
    // ¿El titular es de la ISP principal? Se decide por el id de su ISP (lista
    // ispsPrincipales que envía el servidor), que no depende de cargar la relación.
    const esDePrincipal = (t: Titular) => ispsPrincipales.includes(t.isp_id) || t.isp?.tipo === 'principal';
    // Maneja el estado Corte: la principal y las ISP con planes propios.
    const manejaCorte = (t: Titular) => esDePrincipal(t) || ispsPlanesPropios.includes(t.isp_id);

    const estadoDestino = (t: Titular, c: Cliente): string | null => {
        const alterno = manejaCorte(t) ? 'Corte' : 'Retirado';
        const n = c.estado?.nombre;
        if (n === 'Activo') return alterno;
        if (n === alterno) return 'Activo';
        return null;
    };

    const toggleEstado = (t: Titular, c: Cliente) => {
        const destino = estadoDestino(t, c);
        if (!destino) return;
        // Retirar es una decisión mayor: se confirma antes.
        if (destino === 'Retirado' && !confirm(`¿Retirar el servicio ${c.codigo_cliente}?`)) return;
        router.patch(`/clientes/${c.hashid}/estado`, {}, conservar);
    };

    const mergedErrors = { ...errors, ...clientErrors };
    const columnas = esSuperAdmin ? 9 : 8;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clientes" />

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
                        <h1 className="text-xl font-semibold">Clientes</h1>
                        <p className="text-muted-foreground text-sm">Cada fila es un titular; despliéguela para ver sus servicios.</p>
                    </div>
                    <div className="flex gap-2">
                        {esSuperAdmin && (
                            <Button asChild variant="outline">
                                <a href="/clientes/exportar-facturacion">
                                    <Download className="size-4" /> Exportar facturación
                                </a>
                            </Button>
                        )}
                        {can('clientes.crear') && (
                            <Button onClick={abrirNuevoCliente}>
                                <Plus className="size-4" /> Nuevo cliente
                            </Button>
                        )}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <form onSubmit={buscar} className="flex gap-2">
                        <Input
                            placeholder="Buscar por identificación, nombre, teléfono, correo o código de servicio..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-96 max-w-full"
                        />
                        <Button type="submit" variant="secondary">Buscar</Button>
                    </form>

                    {esSuperAdmin && isps && (
                        <Select value={filtros.isp_id ?? TODOS} onValueChange={(v) => filtrar('isp_id', v)}>
                            <SelectTrigger className="w-48"><SelectValue placeholder="ISP" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>Todas las ISP</SelectItem>
                                {isps.map((isp) => (
                                    <SelectItem key={isp.id} value={isp.hashid ?? ''}>{isp.nombre}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    {/* Filtro por estado del servicio. */}
                    <Select value={filtros.estado ?? TODOS} onValueChange={(v) => filtrar('estado', v)}>
                        <SelectTrigger className="w-48"><SelectValue placeholder="Estado" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value={TODOS}>Todos los estados</SelectItem>
                            {estadosFiltro.map((e) => (
                                <SelectItem key={e} value={e}>{e}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {/* Filtro de puerto alquilado: Super Admin e ISP principal. */}
                    {muestraPuerto && (
                        <Select value={filtros.puerto ?? TODOS} onValueChange={(v) => filtrar('puerto', v)}>
                            <SelectTrigger className="w-48"><SelectValue placeholder="Puerto" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem value={TODOS}>Todos los puertos</SelectItem>
                                <SelectItem value="1">Puerto alquilado</SelectItem>
                                <SelectItem value="0">Puerto propio</SelectItem>
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

                    {(filtros.search || filtros.isp_id || filtros.facturable || filtros.estado || filtros.puerto) && (
                        <Button type="button" variant="ghost" onClick={() => { setSearch(''); navegar({}); }}>
                            Limpiar filtros
                        </Button>
                    )}
                </div>

                {/* Totales según el filtro aplicado (Super Admin). */}
                {esSuperAdmin && (
                    <Card className="sm:max-w-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Según el filtro</CardTitle>
                            <Filter className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent className="flex gap-8">
                            <div>
                                <p className="text-3xl font-bold">{titulares.total}</p>
                                <p className="text-muted-foreground text-xs">titulares</p>
                            </div>
                            <div>
                                <p className="text-3xl font-bold">{totalServicios}</p>
                                <p className="text-muted-foreground text-xs">servicios</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <div className="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10" />
                                <TableHead>{thOrden('identificacion', 'Identificación')}</TableHead>
                                <TableHead>{thOrden('nombre', 'Nombres')}</TableHead>
                                <TableHead>{thOrden('apellido', 'Apellidos')}</TableHead>
                                {esSuperAdmin && <TableHead>ISP</TableHead>}
                                <TableHead>Teléfono</TableHead>
                                <TableHead>Correo</TableHead>
                                <TableHead className="text-center">{thOrden('servicios', 'Servicios', 'w-full justify-center')}</TableHead>
                                <TableHead className="w-28 text-right">Acciones</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {titulares.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={columnas} className="text-muted-foreground py-8 text-center">
                                        No hay clientes registrados.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                titulares.data.map((titular) => {
                                    const abierto = abiertos.has(titular.id);

                                    return (
                                        <Fragment key={titular.id}>
                                            {/* --- Fila del titular (la persona) --- */}
                                            <TableRow className="cursor-pointer" onClick={() => alternar(titular.id)}>
                                                <TableCell>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="icon"
                                                        className="size-7"
                                                        aria-label={abierto ? 'Ocultar servicios' : 'Ver servicios'}
                                                        aria-expanded={abierto}
                                                        onClick={(e) => {
                                                            e.stopPropagation();
                                                            alternar(titular.id);
                                                        }}
                                                    >
                                                        {abierto ? <ChevronDown className="size-4" /> : <ChevronRight className="size-4" />}
                                                    </Button>
                                                </TableCell>
                                                <TableCell className="font-medium tabular-nums">
                                                    <span className="text-muted-foreground mr-1 text-xs">{titular.tipo_identificacion}</span>
                                                    {titular.identificacion}
                                                </TableCell>
                                                <TableCell>{nombres(titular)}</TableCell>
                                                <TableCell>{apellidos(titular) || '—'}</TableCell>
                                                {esSuperAdmin && <TableCell>{titular.isp?.nombre ?? '—'}</TableCell>}
                                                <TableCell className="whitespace-nowrap tabular-nums">
                                                    {titular.telefono_1 ?? '—'}
                                                    {titular.telefono_2 ? ` / ${titular.telefono_2}` : ''}
                                                </TableCell>
                                                <TableCell className="max-w-56 truncate" title={titular.correo ?? ''}>{titular.correo || '—'}</TableCell>
                                                <TableCell className="text-center">
                                                    <span className="bg-muted inline-flex min-w-7 justify-center rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums">
                                                        {titular.servicios_count}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right" onClick={(e) => e.stopPropagation()}>
                                                    <div className="flex justify-end gap-1">
                                                        {can('clientes.crear') && (
                                                            <Button variant="ghost" size="icon" title="Agregar servicio" onClick={() => abrirAgregarServicio(titular)}>
                                                                <Plus className="size-4" />
                                                            </Button>
                                                        )}
                                                        {can('clientes.editar') && (
                                                            <Button variant="ghost" size="icon" title="Editar titular" onClick={() => abrirEditarTitular(titular)}>
                                                                <UserCog className="size-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>

                                            {/* --- Desplegable: servicios a nombre del titular --- */}
                                            {abierto && (
                                                <TableRow className="hover:bg-transparent">
                                                    <TableCell colSpan={columnas} className="bg-muted/40 dark:bg-muted/60 p-4">
                                                        <div className="mb-3 flex items-center justify-between">
                                                            <h3 className="text-sm font-semibold">
                                                                Servicio{titular.clientes.length === 1 ? '' : 's'} de {nombreCompleto(titular)}
                                                            </h3>
                                                            {can('clientes.crear') && (
                                                                <Button size="sm" variant="outline" onClick={() => abrirAgregarServicio(titular)}>
                                                                    <Plus className="size-4" /> Agregar servicio
                                                                </Button>
                                                            )}
                                                        </div>

                                                        <div className="bg-card overflow-hidden rounded-lg border">
                                                            <Table>
                                                                <TableHeader>
                                                                    <TableRow>
                                                                        <TableHead>Código</TableHead>
                                                                        <TableHead>Estado</TableHead>
                                                                        <TableHead>Barrio</TableHead>
                                                                        <TableHead>Dirección</TableHead>
                                                                        <TableHead>Plan</TableHead>
                                                                        <TableHead className="text-right">Valor</TableHead>
                                                                        <TableHead>Ciudad</TableHead>
                                                                        <TableHead className="text-center">Día de pago</TableHead>
                                                                        <TableHead>Instalación</TableHead>
                                                                        {esSuperAdmin && <TableHead>Facturable</TableHead>}
                                                                        <TableHead className="w-16 text-right">Acciones</TableHead>
                                                                    </TableRow>
                                                                </TableHeader>
                                                                <TableBody>
                                                                    {titular.clientes.map((servicio) => {
                                                                        const destino = estadoDestino(titular, servicio);
                                                                        const color = servicio.estado?.color ?? '#e5e7eb';

                                                                        return (
                                                                            <TableRow
                                                                                key={servicio.id}
                                                                                /* Tinte del color del estado: más suave en claro, algo más
                                                                                   fuerte en oscuro para que se note sobre el fondo. */
                                                                                className="bg-[color-mix(in_srgb,var(--estado)_12%,transparent)] hover:bg-[color-mix(in_srgb,var(--estado)_18%,transparent)] dark:bg-[color-mix(in_srgb,var(--estado)_16%,transparent)] dark:hover:bg-[color-mix(in_srgb,var(--estado)_24%,transparent)]"
                                                                                style={{ '--estado': color, boxShadow: `inset 3px 0 0 ${color}` } as CSSProperties}
                                                                            >
                                                                                <TableCell className="font-medium">
                                                                                    <span className="flex items-center gap-2 whitespace-nowrap">
                                                                                        {servicio.codigo_cliente}
                                                                                        {servicio.puerto_alquilado && (
                                                                                            <span
                                                                                                className="rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
                                                                                                title="Servicio sobre un puerto alquilado a una ISP externa"
                                                                                            >
                                                                                                Puerto alquilado
                                                                                            </span>
                                                                                        )}
                                                                                        {(servicio.tickets_abiertos ?? 0) > 0 && (
                                                                                            <button
                                                                                                type="button"
                                                                                                onClick={() => navegarConFiltros('/tickets', { search: servicio.codigo_cliente })}
                                                                                                className="flex items-center gap-1 rounded-full border border-red-300 bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-800 hover:bg-red-100 dark:border-red-800 dark:bg-red-950 dark:text-red-300"
                                                                                                title="Ver tickets abiertos de este servicio"
                                                                                            >
                                                                                                <LifeBuoy className="size-3" />
                                                                                                {servicio.tickets_abiertos} {servicio.tickets_abiertos === 1 ? 'ticket' : 'tickets'}
                                                                                            </button>
                                                                                        )}
                                                                                    </span>
                                                                                </TableCell>
                                                                                <TableCell>
                                                                                    {can('clientes.editar') && destino ? (
                                                                                        <button
                                                                                            type="button"
                                                                                            onClick={() => toggleEstado(titular, servicio)}
                                                                                            title={`Clic para cambiar a ${destino}`}
                                                                                            className="hover:bg-foreground/10 flex items-center gap-2 rounded px-1.5 py-0.5 transition"
                                                                                        >
                                                                                            <span className="size-2.5 rounded-full border" style={{ backgroundColor: color }} />
                                                                                            {servicio.estado?.nombre}
                                                                                        </button>
                                                                                    ) : (
                                                                                        <span className="flex items-center gap-2">
                                                                                            <span className="size-2.5 rounded-full border" style={{ backgroundColor: color }} />
                                                                                            {servicio.estado?.nombre ?? '—'}
                                                                                        </span>
                                                                                    )}
                                                                                </TableCell>
                                                                                <TableCell>{servicio.barrio?.nombre ?? '—'}</TableCell>
                                                                                <TableCell className="max-w-56 truncate" title={servicio.direccion}>
                                                                                    {servicio.direccion || '—'}
                                                                                </TableCell>
                                                                                <TableCell className="whitespace-nowrap">{nombrePlan(servicio)}</TableCell>
                                                                                <TableCell className="text-right tabular-nums">{formatearValor(servicio.plan?.valor)}</TableCell>
                                                                                <TableCell>{servicio.barrio?.ciudad?.nombre ?? '—'}</TableCell>
                                                                                <TableCell className="text-center tabular-nums">{servicio.dia_corte ?? '—'}</TableCell>
                                                                                <TableCell className="tabular-nums">
                                                                                    {servicio.fecha_instalacion ? servicio.fecha_instalacion.slice(0, 10) : '—'}
                                                                                </TableCell>
                                                                                {esSuperAdmin && (
                                                                                    <TableCell>
                                                                                        <button
                                                                                            type="button"
                                                                                            onClick={() => toggleFacturable(servicio)}
                                                                                            title={`${servicio.facturable ? 'Facturable' : 'No facturable'} — clic para cambiar`}
                                                                                            className="hover:ring-foreground/40 ring-offset-background inline-block size-3.5 rounded-full ring-offset-2 transition hover:ring-2"
                                                                                            style={{ backgroundColor: servicio.facturable ? '#22c55e' : '#ef4444' }}
                                                                                        />
                                                                                    </TableCell>
                                                                                )}
                                                                                <TableCell className="text-right">
                                                                                    {/* Opciones del servicio en un menú desplegable. modal={false}: así
                                                                                        los modales que abre (comentarios, ticket, editar) no quedan bloqueados. */}
                                                                                    <DropdownMenu modal={false}>
                                                                                        <DropdownMenuTrigger asChild>
                                                                                            <Button variant="ghost" size="icon" title="Opciones del servicio">
                                                                                                <MoreHorizontal className="size-4" />
                                                                                            </Button>
                                                                                        </DropdownMenuTrigger>
                                                                                        <DropdownMenuContent align="end" className="w-52">
                                                                                            <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">
                                                                                                Servicio {servicio.codigo_cliente}
                                                                                            </DropdownMenuLabel>
                                                                                            {servicio.tiene_documento && (
                                                                                                <DropdownMenuItem asChild>
                                                                                                    <a href={urlDocumento(servicio)} target="_blank" rel="noreferrer">
                                                                                                        <FileText className="size-4" /> Ver documento
                                                                                                    </a>
                                                                                                </DropdownMenuItem>
                                                                                            )}
                                                                                            <DropdownMenuItem onSelect={() => abrirComentarios(servicio)}>
                                                                                                <MessageSquare className="size-4" /> Comentarios
                                                                                            </DropdownMenuItem>
                                                                                            {manejaPagos(servicio.isp_id) && (
                                                                                                <DropdownMenuItem
                                                                                                    onSelect={() =>
                                                                                                        setPagosDe({
                                                                                                            hashid: servicio.hashid,
                                                                                                            codigo: servicio.codigo_cliente,
                                                                                                            titular: nombreCompleto(titular),
                                                                                                        })
                                                                                                    }
                                                                                                >
                                                                                                    <Receipt className="size-4" /> Pagos y comprobantes
                                                                                                </DropdownMenuItem>
                                                                                            )}
                                                                                            {tiposFallaDe(servicio.isp_id).length > 0 && (
                                                                                                <DropdownMenuItem
                                                                                                    onSelect={() =>
                                                                                                        setTicketDe({
                                                                                                            hashid: servicio.hashid,
                                                                                                            codigo: servicio.codigo_cliente,
                                                                                                            titular: nombreCompleto(titular),
                                                                                                            isp_id: servicio.isp_id,
                                                                                                        })
                                                                                                    }
                                                                                                >
                                                                                                    <LifeBuoy className="size-4" /> Nuevo ticket de soporte
                                                                                                </DropdownMenuItem>
                                                                                            )}
                                                                                            {can('clientes.editar') && (
                                                                                                <DropdownMenuItem onSelect={() => abrirEditarServicio(titular, servicio)}>
                                                                                                    <Pencil className="size-4" /> Editar servicio
                                                                                                </DropdownMenuItem>
                                                                                            )}
                                                                                            {can('clientes.eliminar') && (
                                                                                                <>
                                                                                                    <DropdownMenuSeparator />
                                                                                                    <DropdownMenuItem
                                                                                                        onSelect={() => eliminar(servicio)}
                                                                                                        className="text-destructive focus:text-destructive"
                                                                                                    >
                                                                                                        <Trash2 className="text-destructive size-4" /> Eliminar servicio
                                                                                                    </DropdownMenuItem>
                                                                                                </>
                                                                                            )}
                                                                                        </DropdownMenuContent>
                                                                                    </DropdownMenu>
                                                                                </TableCell>
                                                                            </TableRow>
                                                                        );
                                                                    })}
                                                                </TableBody>
                                                            </Table>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </Fragment>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex items-center justify-between">
                    <p className="text-muted-foreground text-sm">
                        {titulares.total} {titulares.total === 1 ? 'titular' : 'titulares'} en total
                    </p>
                    <Pagination links={titulares.links} />
                </div>
            </div>

            {/* Modal: nuevo cliente / editar titular / agregar o editar servicio */}
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{tituloModal}</DialogTitle>
                        <DialogDescription>{descripcionModal}</DialogDescription>
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
                            documentoUrl={docActual}
                            ispId={ispFormulario}
                            esIspCliente={esIspCliente}
                            planAutomatico={planAutomatico}
                            documentoObligatorio={creandoServicio && !esIspCliente}
                            modo={modo}
                        />
                        {mergedErrors.titular && <p className="text-destructive text-sm">{mergedErrors.titular}</p>}
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setOpen(false)}>Cancelar</Button>
                            <Button type="submit" disabled={processing}>
                                {modo === 'completo' ? 'Guardar cliente' : modo === 'titular' ? 'Guardar titular' : servicioSel ? 'Guardar cambios' : 'Agregar servicio'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Modal de comentarios (por servicio) */}
            <Dialog open={comentariosOpen} onOpenChange={setComentariosOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Comentarios — {comentariosCliente?.codigo_cliente}</DialogTitle>
                        <DialogDescription>Seguimiento y observaciones del servicio.</DialogDescription>
                    </DialogHeader>

                    {/* Pestañas */}
                    <div className="flex gap-2 border-b">
                        <button
                            type="button"
                            onClick={() => setTabComentario('seguimiento')}
                            className={`-mb-px border-b-2 px-3 py-2 text-sm ${tabComentario === 'seguimiento' ? 'border-primary font-medium' : 'text-muted-foreground border-transparent'}`}
                        >
                            Seguimiento
                        </button>
                        {puedeFacturacion && (
                            <button
                                type="button"
                                onClick={() => setTabComentario('facturacion')}
                                className={`-mb-px border-b-2 px-3 py-2 text-sm ${tabComentario === 'facturacion' ? 'border-primary font-medium' : 'text-muted-foreground border-transparent'}`}
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
                                                <Button type="button" size="sm" onClick={() => guardarEdicion(c.hashid)}>Guardar</Button>
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
                                                        <button type="button" onClick={() => borrarComentario(c.hashid)} className="text-destructive hover:underline">
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
                            <Button type="submit" disabled={enviandoComentario || !nuevoComentario.trim()}>Agregar</Button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Pagos del servicio (opciones del servicio) */}
            {pagos && <PagosDialog servicio={pagosDe} onClose={() => setPagosDe(null)} />}

            {/* Nuevo ticket de soporte (opciones del servicio) */}
            {tickets && (
                <NuevoTicketDialog
                    servicio={ticketDe}
                    onClose={() => setTicketDe(null)}
                    tiposFalla={ticketDe ? tiposFallaDe(ticketDe.isp_id) : []}
                    prioridades={tickets.prioridades}
                />
            )}
        </AppLayout>
    );
}
