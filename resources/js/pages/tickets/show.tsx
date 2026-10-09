import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, CircleCheck, MessageSquare, PencilLine, PlusCircle, RotateCcw } from 'lucide-react';
import { FormEventHandler, useState } from 'react';
import { EstadoTicketBadge, PrioridadBadge, type Prioridad, type TicketResumen } from './index';

interface TicketDetalle extends TicketResumen {
    descripcion: string;
    solucion: string | null;
    tipo_falla_id: number;
    fecha_visita_input: string | null;
    cerrador: string | null;
    cerrado_at: string | null;
    servicio: TicketResumen['servicio'] & {
        barrio: string | null;
        plan: string | null;
        estado: string | null;
        estado_color: string | null;
        telefono: string | null;
    };
}

interface Evento {
    id: number;
    tipo: 'creado' | 'comentario' | 'cambio' | 'cerrado' | 'reabierto';
    contenido: string | null;
    autor: string;
    fecha: string;
}

interface Props {
    ticket: TicketDetalle;
    eventos: Evento[];
    tiposFalla: { id: number; nombre: string }[];
    prioridades: Prioridad[];
    puede: { comentar: boolean; editar: boolean; cerrar: boolean };
}

// Cómo se muestra cada tipo de evento en el historial.
const EVENTO = {
    creado: { texto: 'abrió el ticket', icono: PlusCircle, color: 'text-blue-600 dark:text-blue-400' },
    comentario: { texto: 'comentó', icono: MessageSquare, color: 'text-muted-foreground' },
    cambio: { texto: 'cambió', icono: PencilLine, color: 'text-amber-600 dark:text-amber-400' },
    cerrado: { texto: 'cerró el ticket', icono: CircleCheck, color: 'text-green-600 dark:text-green-400' },
    reabierto: { texto: 'reabrió el ticket', icono: RotateCcw, color: 'text-red-600 dark:text-red-400' },
} as const;

const textareaClase =
    'border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none';

export default function TicketShow({ ticket, eventos, tiposFalla, prioridades, puede }: Props) {
    const { flash, errors: erroresPagina } = usePage<SharedData & { errors: Record<string, string> }>().props;
    const abierto = ticket.estado === 'abierto';

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Tickets de soporte', href: '/tickets' },
        { title: `#${ticket.numero}`, href: `/tickets/${ticket.hashid}` },
    ];

    const conservar = { preserveScroll: true };

    // --- Datos editables (prioridad, tipo, fecha de visita) ---
    const edicion = useForm({
        tipo_falla_id: String(ticket.tipo_falla_id),
        prioridad: ticket.prioridad,
        fecha_visita: ticket.fecha_visita_input ?? '',
    });
    const huboCambios =
        edicion.data.tipo_falla_id !== String(ticket.tipo_falla_id) ||
        edicion.data.prioridad !== ticket.prioridad ||
        edicion.data.fecha_visita !== (ticket.fecha_visita_input ?? '');
    const guardarDatos: FormEventHandler = (e) => {
        e.preventDefault();
        edicion.put(`/tickets/${ticket.hashid}`, conservar);
    };

    // --- Comentario ---
    const comentario = useForm({ contenido: '' });
    const comentar: FormEventHandler = (e) => {
        e.preventDefault();
        comentario.post(`/tickets/${ticket.hashid}/comentarios`, { ...conservar, onSuccess: () => comentario.reset() });
    };

    // --- Cerrar / reabrir ---
    const [cierreOpen, setCierreOpen] = useState(false);
    const cierre = useForm({ solucion: '', motivo: '' });
    const confirmarCierre: FormEventHandler = (e) => {
        e.preventDefault();
        const url = abierto ? `/tickets/${ticket.hashid}/cerrar` : `/tickets/${ticket.hashid}/reabrir`;
        cierre.post(url, { ...conservar, onSuccess: () => { setCierreOpen(false); cierre.reset(); } });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Ticket #${ticket.numero}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {flash.success && (
                    <div className="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                        {flash.success}
                    </div>
                )}
                {(flash.error || erroresPagina?.ticket) && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                        {flash.error ?? erroresPagina.ticket}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex items-center gap-3">
                        <Button variant="ghost" size="icon" asChild>
                            <Link href="/tickets" aria-label="Volver a tickets"><ArrowLeft className="size-4" /></Link>
                        </Button>
                        <div>
                            <h1 className="flex items-center gap-3 text-xl font-semibold">
                                Ticket #{ticket.numero}
                                <EstadoTicketBadge estado={ticket.estado} label={ticket.estado_label} />
                                <PrioridadBadge label={ticket.prioridad_label} color={ticket.prioridad_color} />
                            </h1>
                            <p className="text-muted-foreground text-sm">
                                Abierto el {ticket.fecha}
                                {ticket.creador && ` por ${ticket.creador}`}
                                {ticket.isp && ` · ${ticket.isp}`}
                            </p>
                        </div>
                    </div>
                    {puede.cerrar && (
                        <Button variant={abierto ? 'default' : 'outline'} onClick={() => { cierre.clearErrors(); setCierreOpen(true); }}>
                            {abierto ? <><CircleCheck className="size-4" /> Cerrar ticket</> : <><RotateCcw className="size-4" /> Reabrir</>}
                        </Button>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* --- Columna izquierda: servicio y datos --- */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader className="pb-2"><CardTitle className="text-sm font-medium">Servicio</CardTitle></CardHeader>
                            <CardContent className="space-y-1 text-sm">
                                <p className="text-base font-semibold">{ticket.servicio.codigo}</p>
                                <p>{ticket.servicio.titular}</p>
                                <p className="text-muted-foreground tabular-nums">{ticket.servicio.identificacion}</p>
                                {ticket.servicio.telefono && <p className="tabular-nums">{ticket.servicio.telefono}</p>}
                                <p>{ticket.servicio.direccion}{ticket.servicio.barrio && ` · ${ticket.servicio.barrio}`}</p>
                                {ticket.servicio.plan && <p className="text-muted-foreground">{ticket.servicio.plan}</p>}
                                {ticket.servicio.estado && (
                                    <p className="flex items-center gap-2">
                                        <span className="size-2.5 rounded-full border" style={{ backgroundColor: ticket.servicio.estado_color ?? '#e5e7eb' }} />
                                        {ticket.servicio.estado}
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2"><CardTitle className="text-sm font-medium">Datos del ticket</CardTitle></CardHeader>
                            <CardContent>
                                <form onSubmit={guardarDatos} className="space-y-3">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="tipo_falla_id">Tipo de falla</Label>
                                        <Select value={edicion.data.tipo_falla_id} onValueChange={(v) => edicion.setData('tipo_falla_id', v)} disabled={!puede.editar || !abierto}>
                                            <SelectTrigger id="tipo_falla_id"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                {tiposFalla.map((t) => (
                                                    <SelectItem key={t.id} value={String(t.id)}>{t.nombre}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={edicion.errors.tipo_falla_id} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="prioridad">Prioridad</Label>
                                        <Select value={edicion.data.prioridad} onValueChange={(v) => edicion.setData('prioridad', v)} disabled={!puede.editar || !abierto}>
                                            <SelectTrigger id="prioridad"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                {prioridades.map((p) => (
                                                    <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="fecha_visita">Fecha de visita</Label>
                                        <Input
                                            id="fecha_visita"
                                            type="datetime-local"
                                            value={edicion.data.fecha_visita}
                                            onChange={(e) => edicion.setData('fecha_visita', e.target.value)}
                                            disabled={!puede.editar || !abierto}
                                        />
                                        <InputError message={edicion.errors.fecha_visita} />
                                    </div>
                                    {puede.editar && abierto && (
                                        <Button type="submit" size="sm" className="w-full" disabled={!huboCambios || edicion.processing}>
                                            Guardar cambios
                                        </Button>
                                    )}
                                </form>
                            </CardContent>
                        </Card>

                        {!abierto && ticket.solucion && (
                            <Card className="border-green-200 dark:border-green-900">
                                <CardHeader className="pb-2">
                                    <CardTitle className="text-sm font-medium text-green-700 dark:text-green-400">Solución</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    <p className="whitespace-pre-wrap">{ticket.solucion}</p>
                                    <p className="text-muted-foreground text-xs">
                                        Cerrado el {ticket.cerrado_at}{ticket.cerrador && ` por ${ticket.cerrador}`}
                                    </p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* --- Columna derecha: descripción e historial --- */}
                    <div className="space-y-4 lg:col-span-2">
                        <Card>
                            <CardHeader className="pb-2"><CardTitle className="text-sm font-medium">Descripción</CardTitle></CardHeader>
                            <CardContent>
                                <p className="text-sm whitespace-pre-wrap">{ticket.descripcion}</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="pb-2"><CardTitle className="text-sm font-medium">Historial</CardTitle></CardHeader>
                            <CardContent>
                                <ol className="relative space-y-4 border-l pl-6">
                                    {eventos.map((e) => {
                                        const info = EVENTO[e.tipo] ?? EVENTO.comentario;
                                        const Icono = info.icono;
                                        return (
                                            <li key={e.id} className="relative">
                                                <span className="bg-card absolute top-0.5 -left-[2.05rem] flex size-5 items-center justify-center rounded-full">
                                                    <Icono className={`size-4 ${info.color}`} />
                                                </span>
                                                <p className="text-sm">
                                                    <span className="font-medium">{e.autor}</span>{' '}
                                                    <span className="text-muted-foreground">{info.texto}</span>
                                                    <span className="text-muted-foreground ml-2 text-xs tabular-nums">{e.fecha}</span>
                                                </p>
                                                {e.contenido && e.tipo !== 'creado' && (
                                                    <p className="bg-muted/50 mt-1 rounded-md px-3 py-2 text-sm whitespace-pre-wrap">{e.contenido}</p>
                                                )}
                                            </li>
                                        );
                                    })}
                                </ol>

                                {puede.comentar && (
                                    <form onSubmit={comentar} className="mt-6 space-y-2">
                                        <textarea
                                            rows={3}
                                            value={comentario.data.contenido}
                                            onChange={(e) => comentario.setData('contenido', e.target.value)}
                                            placeholder="Agregar un seguimiento: qué se revisó, qué dijo el cliente, qué sigue..."
                                            className={textareaClase}
                                        />
                                        <InputError message={comentario.errors.contenido} />
                                        <div className="flex justify-end">
                                            <Button type="submit" size="sm" disabled={comentario.processing || !comentario.data.contenido.trim()}>
                                                Agregar seguimiento
                                            </Button>
                                        </div>
                                    </form>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Modal: cerrar (pide solución) o reabrir (pide motivo) */}
            <Dialog open={cierreOpen} onOpenChange={setCierreOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{abierto ? `Cerrar ticket #${ticket.numero}` : `Reabrir ticket #${ticket.numero}`}</DialogTitle>
                        <DialogDescription>
                            {abierto ? 'Describa la solución: queda en el historial del servicio.' : 'Indique por qué se reabre.'}
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={confirmarCierre} className="space-y-3">
                        <textarea
                            rows={4}
                            autoFocus
                            value={abierto ? cierre.data.solucion : cierre.data.motivo}
                            onChange={(e) => cierre.setData(abierto ? 'solucion' : 'motivo', e.target.value)}
                            className={textareaClase}
                        />
                        <InputError message={abierto ? cierre.errors.solucion : cierre.errors.motivo} />
                        <DialogFooter>
                            <Button type="button" variant="ghost" onClick={() => setCierreOpen(false)}>Cancelar</Button>
                            <Button type="submit" disabled={cierre.processing}>{abierto ? 'Cerrar ticket' : 'Reabrir'}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
