import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Ban, FileDown, Receipt } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface PagoFila {
    id: number;
    hashid: string;
    numero: string;
    periodo: string;
    periodo_texto: string;
    valor: string;
    fecha_pago: string;
    medio_pago: string;
    referencia: string | null;
    observacion: string | null;
    registrado_por: string | null;
    registrado_at: string | null;
    anulado: boolean;
    anulado_at: string | null;
    anulado_por: string | null;
    motivo_anulacion: string | null;
    url: string;
}

interface DatosPagos {
    pagos: PagoFila[];
    activo: boolean;
    estado: string | null;
    sugerido: { periodo: string; valor: string; fecha_pago: string };
    medios: { value: string; label: string }[];
    puede: { registrar: boolean; anular: boolean };
}

interface Props {
    servicio: { hashid: string; codigo: string; titular: string } | null;
    onClose: () => void;
}

type Errores = Record<string, string>;

const pesos = (valor: string | number) =>
    new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(valor));

// Petición JSON con la cookie de sesión y el token CSRF de Laravel (XSRF-TOKEN).
async function pedir(url: string, metodo: 'GET' | 'POST' = 'GET', cuerpo?: unknown) {
    const xsrf = document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1];
    const respuesta = await fetch(url, {
        method: metodo,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(xsrf ? { 'X-XSRF-TOKEN': decodeURIComponent(xsrf) } : {}),
        },
        body: cuerpo ? JSON.stringify(cuerpo) : undefined,
    });
    const json = await respuesta.json().catch(() => ({}));
    return { ok: respuesta.ok, status: respuesta.status, json };
}

// Errores de validación de Laravel ({ errors: { campo: [msg] } }) a { campo: msg }.
const aErrores = (json: { errors?: Record<string, string[]>; message?: string }): Errores =>
    json.errors ? Object.fromEntries(Object.entries(json.errors).map(([k, v]) => [k, v[0]])) : { general: json.message ?? 'No se pudo completar la operación.' };

const FORM_VACIO = { periodo: '', valor: '', fecha_pago: '', medio_pago: 'efectivo', referencia: '', observacion: '' };

/**
 * Modal "Pagos" de un servicio: registra el pago de un mes, genera el
 * comprobante en PDF y muestra el historial (con anulaciones).
 */
export function PagosDialog({ servicio, onClose }: Props) {
    const [datos, setDatos] = useState<DatosPagos | null>(null);
    const [cargando, setCargando] = useState(false);
    const [form, setForm] = useState(FORM_VACIO);
    const [errores, setErrores] = useState<Errores>({});
    const [guardando, setGuardando] = useState(false);
    const [ultimo, setUltimo] = useState<PagoFila | null>(null); // recién registrado
    const [anulando, setAnulando] = useState<PagoFila | null>(null);
    const [motivo, setMotivo] = useState('');

    const cargar = async (hashid: string) => {
        setCargando(true);
        const r = await pedir(`/clientes/${hashid}/pagos`);
        setCargando(false);
        if (!r.ok) {
            setErrores(aErrores(r.json));
            return;
        }
        const d = r.json as DatosPagos;
        setDatos(d);
        setForm({ ...FORM_VACIO, ...d.sugerido });
    };

    useEffect(() => {
        setDatos(null);
        setErrores({});
        setUltimo(null);
        setAnulando(null);
        if (servicio) cargar(servicio.hashid);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [servicio?.hashid]);

    const campo = (clave: keyof typeof FORM_VACIO, valor: string) => setForm((f) => ({ ...f, [clave]: valor }));

    const registrar = async (e: FormEvent) => {
        e.preventDefault();
        if (!servicio) return;
        setGuardando(true);
        setErrores({});
        const r = await pedir(`/clientes/${servicio.hashid}/pagos`, 'POST', form);
        setGuardando(false);
        if (!r.ok) {
            setErrores(aErrores(r.json));
            return;
        }
        setUltimo(r.json.pago as PagoFila);
        await cargar(servicio.hashid);
    };

    const anular = async (e: FormEvent) => {
        e.preventDefault();
        if (!servicio || !anulando) return;
        setGuardando(true);
        const r = await pedir(`/pagos/${anulando.hashid}/anular`, 'POST', { motivo });
        setGuardando(false);
        if (!r.ok) {
            setErrores(aErrores(r.json));
            return;
        }
        setAnulando(null);
        setMotivo('');
        setUltimo(null);
        await cargar(servicio.hashid);
    };

    return (
        <Dialog open={servicio !== null} onOpenChange={(abierto) => !abierto && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Pagos del servicio {servicio?.codigo}</DialogTitle>
                    <DialogDescription>{servicio?.titular}</DialogDescription>
                </DialogHeader>

                {cargando && !datos && <p className="text-muted-foreground py-6 text-center text-sm">Cargando...</p>}
                {errores.general && <InputError message={errores.general} />}

                {datos && (
                    <div className="space-y-5">
                        {ultimo && (
                            <div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-300">
                                <span>
                                    Pago de {ultimo.periodo_texto} registrado. Comprobante <strong>{ultimo.numero}</strong>.
                                </span>
                                <Button size="sm" asChild>
                                    <a href={ultimo.url} target="_blank" rel="noreferrer">
                                        <FileDown className="size-4" /> Abrir comprobante PDF
                                    </a>
                                </Button>
                            </div>
                        )}

                        {/* --- Registrar pago --- */}
                        {datos.puede.registrar && !datos.activo && (
                            <div className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                El servicio está en <strong>{datos.estado ?? 'sin estado'}</strong>: solo se registran pagos de servicios activos.
                            </div>
                        )}

                        {datos.puede.registrar && datos.activo && (
                            <form onSubmit={registrar} className="space-y-3 rounded-lg border p-4">
                                <p className="flex items-center gap-2 text-sm font-medium">
                                    <Receipt className="size-4" /> Registrar pago
                                </p>
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="periodo">Mes que paga</Label>
                                        <Input id="periodo" type="month" value={form.periodo} onChange={(e) => campo('periodo', e.target.value)} />
                                        <InputError message={errores.periodo} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="valor">Valor</Label>
                                        <Input id="valor" type="number" min={1} step="1" value={form.valor} onChange={(e) => campo('valor', e.target.value)} />
                                        <InputError message={errores.valor} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="fecha_pago">Fecha de pago</Label>
                                        <Input id="fecha_pago" type="date" value={form.fecha_pago} onChange={(e) => campo('fecha_pago', e.target.value)} />
                                        <InputError message={errores.fecha_pago} />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="medio_pago">Medio de pago</Label>
                                        <Select value={form.medio_pago} onValueChange={(v) => campo('medio_pago', v)}>
                                            <SelectTrigger id="medio_pago"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                {datos.medios.map((m) => (
                                                    <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errores.medio_pago} />
                                    </div>
                                    <div className="grid gap-1.5 sm:col-span-2">
                                        <Label htmlFor="referencia">Referencia (opcional)</Label>
                                        <Input id="referencia" maxLength={60} value={form.referencia} onChange={(e) => campo('referencia', e.target.value)} placeholder="N.° de transferencia o consignación" />
                                        <InputError message={errores.referencia} />
                                    </div>
                                </div>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="observacion">Observación (opcional)</Label>
                                    <Input id="observacion" maxLength={500} value={form.observacion} onChange={(e) => campo('observacion', e.target.value)} />
                                    <InputError message={errores.observacion} />
                                </div>
                                <div className="flex justify-end">
                                    <Button type="submit" disabled={guardando}>Registrar pago y generar comprobante</Button>
                                </div>
                            </form>
                        )}

                        {/* --- Historial --- */}
                        <div>
                            <p className="mb-2 text-sm font-medium">Historial de pagos</p>
                            <div className="rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Comprobante</TableHead>
                                            <TableHead>Mes</TableHead>
                                            <TableHead className="text-right">Valor</TableHead>
                                            <TableHead>Pagado</TableHead>
                                            <TableHead>Medio</TableHead>
                                            <TableHead className="w-24 text-right">Acciones</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {datos.pagos.length === 0 ? (
                                            <TableRow>
                                                <TableCell colSpan={6} className="text-muted-foreground py-6 text-center">Este servicio no tiene pagos registrados.</TableCell>
                                            </TableRow>
                                        ) : (
                                            datos.pagos.map((p) => (
                                                <TableRow key={p.id} className={p.anulado ? 'opacity-60' : ''}>
                                                    <TableCell className="font-medium whitespace-nowrap">
                                                        {p.numero}
                                                        {p.anulado && (
                                                            <span
                                                                className="ml-2 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold text-red-700 dark:bg-red-950 dark:text-red-300"
                                                                title={`Anulado ${p.anulado_at ?? ''} por ${p.anulado_por ?? '—'}: ${p.motivo_anulacion ?? ''}`}
                                                            >
                                                                Anulado
                                                            </span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="whitespace-nowrap">{p.periodo_texto}</TableCell>
                                                    <TableCell className="text-right tabular-nums">{pesos(p.valor)}</TableCell>
                                                    <TableCell className="text-muted-foreground text-xs whitespace-nowrap">
                                                        {p.fecha_pago}
                                                        {p.registrado_por && <div>por {p.registrado_por}</div>}
                                                    </TableCell>
                                                    <TableCell className="text-xs">
                                                        {p.medio_pago}
                                                        {p.referencia && <div className="text-muted-foreground">Ref. {p.referencia}</div>}
                                                    </TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex justify-end gap-1">
                                                            <Button variant="ghost" size="icon" title="Comprobante PDF" asChild>
                                                                <a href={p.url} target="_blank" rel="noreferrer">
                                                                    <FileDown className="size-4" />
                                                                </a>
                                                            </Button>
                                                            {datos.puede.anular && !p.anulado && (
                                                                <Button variant="ghost" size="icon" title="Anular pago" onClick={() => { setAnulando(p); setMotivo(''); setErrores({}); }}>
                                                                    <Ban className="text-destructive size-4" />
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
                        </div>

                        {/* --- Anular --- */}
                        {anulando && (
                            <form onSubmit={anular} className="space-y-3 rounded-lg border border-red-200 p-4 dark:border-red-900">
                                <p className="text-sm">
                                    Anular el comprobante <strong>{anulando.numero}</strong> ({anulando.periodo_texto}, {pesos(anulando.valor)}). Queda en el historial
                                    marcado como anulado y el mes se puede volver a registrar.
                                </p>
                                <div className="grid gap-1.5">
                                    <Label htmlFor="motivo">Motivo</Label>
                                    <Input id="motivo" maxLength={500} value={motivo} onChange={(e) => setMotivo(e.target.value)} autoFocus placeholder="Ej. Valor mal digitado" />
                                    <InputError message={errores.motivo} />
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button type="button" variant="ghost" onClick={() => setAnulando(null)}>Cancelar</Button>
                                    <Button type="submit" variant="destructive" disabled={guardando}>Anular pago</Button>
                                </div>
                            </form>
                        )}
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
