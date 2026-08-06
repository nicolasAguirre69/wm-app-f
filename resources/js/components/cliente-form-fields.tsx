import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type BarrioSelect, type EnumOption, type OpcionIsp, type OpcionSelect } from '@/types';
import { useMemo } from 'react';

export interface ClienteFormValues {
    codigo_cliente: string;
    tipo_identificacion: string;
    identificacion: string;
    tipo_contribuyente: string;
    primer_nombre: string;
    segundo_nombre: string;
    primer_apellido: string;
    segundo_apellido: string;
    telefono_1: string;
    telefono_2: string;
    correo: string;
    ciudad_id: string;
    barrio_id: string;
    direccion: string;
    plan_id: string;
    estado_id: string;
    fecha_instalacion: string;
    dia_corte: string;
    documento_digitalizado: File | null;
}

interface Props {
    data: ClienteFormValues;
    setData: (key: keyof ClienteFormValues, value: string | File | null) => void;
    errors: Partial<Record<string, string>>;
    ciudades: OpcionSelect[];
    barrios: BarrioSelect[];
    planes: OpcionIsp[];
    estados: OpcionIsp[];
    tiposIdentificacion: EnumOption[];
    tiposContribuyente: EnumOption[];
    documentoActual?: string | null; // ruta del documento existente (al editar)
    // Si viene, acota barrios/planes/estados a esa ISP (ISP del cliente).
    ispId?: number | null;
}

export function ClienteFormFields({
    data,
    setData,
    errors,
    ciudades,
    barrios,
    planes,
    estados,
    tiposIdentificacion,
    tiposContribuyente,
    documentoActual,
    ispId = null,
}: Props) {
    // Si hay ispId, acotamos a esa ISP (evita ver catálogos de otras ISPs).
    const planesFiltrados = useMemo(
        () => (ispId ? planes.filter((p) => p.isp_id === ispId) : planes),
        [planes, ispId],
    );
    const estadosFiltrados = useMemo(
        () => (ispId ? estados.filter((e) => e.isp_id === ispId) : estados),
        [estados, ispId],
    );

    // Barrios de la ciudad seleccionada (select encadenado) y de la ISP.
    const barriosFiltrados = useMemo(
        () =>
            barrios.filter(
                (b) => String(b.ciudad_id) === data.ciudad_id && (! ispId || b.isp_id === ispId),
            ),
        [barrios, data.ciudad_id, ispId],
    );

    // Al cambiar de ciudad, limpiamos el barrio elegido.
    const cambiarCiudad = (value: string) => {
        setData('ciudad_id', value);
        setData('barrio_id', '');
    };

    return (
        <div className="space-y-8">
            {/* --- Identificación --- */}
            <section className="space-y-4">
                <h2 className="text-sm font-semibold text-muted-foreground uppercase">Identificación</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="codigo_cliente">Código de cliente</Label>
                        <Input id="codigo_cliente" value={data.codigo_cliente} onChange={(e) => setData('codigo_cliente', e.target.value)} placeholder="Ej. CLI001" />
                        <InputError message={errors.codigo_cliente} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tipo_identificacion">Tipo de identificación</Label>
                        <Select value={data.tipo_identificacion} onValueChange={(v) => setData('tipo_identificacion', v)}>
                            <SelectTrigger id="tipo_identificacion"><SelectValue placeholder="Selecciona" /></SelectTrigger>
                            <SelectContent>
                                {tiposIdentificacion.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.tipo_identificacion} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="identificacion">Número de identificación</Label>
                        <Input id="identificacion" value={data.identificacion} onChange={(e) => setData('identificacion', e.target.value)} />
                        <InputError message={errors.identificacion} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="tipo_contribuyente">Tipo de contribuyente</Label>
                        <Select value={data.tipo_contribuyente} onValueChange={(v) => setData('tipo_contribuyente', v)}>
                            <SelectTrigger id="tipo_contribuyente"><SelectValue placeholder="Selecciona" /></SelectTrigger>
                            <SelectContent>
                                {tiposContribuyente.map((t) => (
                                    <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.tipo_contribuyente} />
                    </div>
                </div>
            </section>

            {/* --- Nombres --- */}
            <section className="space-y-4">
                <h2 className="text-sm font-semibold text-muted-foreground uppercase">Nombres</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="primer_nombre">Primer nombre</Label>
                        <Input id="primer_nombre" value={data.primer_nombre} onChange={(e) => setData('primer_nombre', e.target.value)} />
                        <InputError message={errors.primer_nombre} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="segundo_nombre">Segundo nombre (opcional)</Label>
                        <Input id="segundo_nombre" value={data.segundo_nombre} onChange={(e) => setData('segundo_nombre', e.target.value)} />
                        <InputError message={errors.segundo_nombre} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="primer_apellido">Primer apellido</Label>
                        <Input id="primer_apellido" value={data.primer_apellido} onChange={(e) => setData('primer_apellido', e.target.value)} />
                        <InputError message={errors.primer_apellido} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="segundo_apellido">Segundo apellido</Label>
                        <Input id="segundo_apellido" value={data.segundo_apellido} onChange={(e) => setData('segundo_apellido', e.target.value)} />
                        <InputError message={errors.segundo_apellido} />
                    </div>
                </div>
            </section>

            {/* --- Contacto --- */}
            <section className="space-y-4">
                <h2 className="text-sm font-semibold text-muted-foreground uppercase">Contacto</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="telefono_1">Teléfono 1</Label>
                        <Input id="telefono_1" value={data.telefono_1} onChange={(e) => setData('telefono_1', e.target.value)} />
                        <InputError message={errors.telefono_1} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="telefono_2">Teléfono 2 (opcional)</Label>
                        <Input id="telefono_2" value={data.telefono_2} onChange={(e) => setData('telefono_2', e.target.value)} />
                        <InputError message={errors.telefono_2} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="correo">Correo</Label>
                        <Input id="correo" type="email" value={data.correo} onChange={(e) => setData('correo', e.target.value)} />
                        <InputError message={errors.correo} />
                    </div>
                </div>
            </section>

            {/* --- Ubicación --- */}
            <section className="space-y-4">
                <h2 className="text-sm font-semibold text-muted-foreground uppercase">Ubicación</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="ciudad_id">Ciudad</Label>
                        <Select value={data.ciudad_id} onValueChange={cambiarCiudad}>
                            <SelectTrigger id="ciudad_id"><SelectValue placeholder="Selecciona una ciudad" /></SelectTrigger>
                            <SelectContent>
                                {ciudades.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>{c.nombre}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.ciudad_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="barrio_id">Barrio</Label>
                        <Select value={data.barrio_id} onValueChange={(v) => setData('barrio_id', v)} disabled={!data.ciudad_id}>
                            <SelectTrigger id="barrio_id">
                                <SelectValue placeholder={data.ciudad_id ? 'Selecciona un barrio' : 'Elige una ciudad primero'} />
                            </SelectTrigger>
                            <SelectContent>
                                {barriosFiltrados.map((b) => (
                                    <SelectItem key={b.id} value={String(b.id)}>{b.nombre}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.barrio_id} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="direccion">Dirección</Label>
                        <Input id="direccion" value={data.direccion} onChange={(e) => setData('direccion', e.target.value)} placeholder="Ej. Calle 1 # 2-3" />
                        <InputError message={errors.direccion} />
                    </div>
                </div>
            </section>

            {/* --- Servicio --- */}
            <section className="space-y-4">
                <h2 className="text-sm font-semibold text-muted-foreground uppercase">Servicio</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="plan_id">Plan</Label>
                        <Select value={data.plan_id} onValueChange={(v) => setData('plan_id', v)}>
                            <SelectTrigger id="plan_id"><SelectValue placeholder="Selecciona un plan" /></SelectTrigger>
                            <SelectContent>
                                {planesFiltrados.map((p) => (
                                    <SelectItem key={p.id} value={String(p.id)}>{p.nombre}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.plan_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="estado_id">Estado</Label>
                        <Select value={data.estado_id} onValueChange={(v) => setData('estado_id', v)}>
                            <SelectTrigger id="estado_id"><SelectValue placeholder="Selecciona un estado" /></SelectTrigger>
                            <SelectContent>
                                {estadosFiltrados.map((e) => (
                                    <SelectItem key={e.id} value={String(e.id)}>{e.nombre}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.estado_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="fecha_instalacion">Fecha de instalación</Label>
                        <Input id="fecha_instalacion" type="date" value={data.fecha_instalacion} onChange={(e) => setData('fecha_instalacion', e.target.value)} />
                        <InputError message={errors.fecha_instalacion} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="dia_corte">Día de corte (1-31)</Label>
                        <Input id="dia_corte" type="number" min={1} max={31} value={data.dia_corte} onChange={(e) => setData('dia_corte', e.target.value)} />
                        <InputError message={errors.dia_corte} />
                    </div>
                </div>
            </section>

            {/* --- Documento --- */}
            <section className="space-y-4">
                <h2 className="text-sm font-semibold text-muted-foreground uppercase">Documento</h2>
                <div className="grid gap-2">
                    <Label htmlFor="documento_digitalizado">Documento digitalizado (PDF, JPG o PNG)</Label>
                    <Input
                        id="documento_digitalizado"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        onChange={(e) => setData('documento_digitalizado', e.target.files?.[0] ?? null)}
                    />
                    {documentoActual && (
                        <a href={`/storage/${documentoActual}`} target="_blank" rel="noreferrer" className="text-primary text-sm underline">
                            Ver documento actual
                        </a>
                    )}
                    <InputError message={errors.documento_digitalizado} />
                </div>
            </section>
        </div>
    );
}
