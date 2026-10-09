import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

export interface TipoFallaOpcion {
    id: number;
    isp_id: number;
    nombre: string;
}

export interface PrioridadOpcion {
    value: string;
    label: string;
    color: string;
}

interface Props {
    // Servicio sobre el que se abre el ticket (null = cerrado).
    servicio: { hashid: string; codigo: string; titular: string } | null;
    onClose: () => void;
    tiposFalla: TipoFallaOpcion[]; // ya filtrados a la ISP del servicio
    prioridades: PrioridadOpcion[];
}

/**
 * Modal "Nuevo ticket de soporte" de un servicio. Se abre desde las opciones
 * del servicio en Clientes.
 */
export function NuevoTicketDialog({ servicio, onClose, tiposFalla, prioridades }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        servicio: '',
        tipo_falla_id: '',
        prioridad: 'media',
        descripcion: '',
        fecha_visita: '',
    });

    // Cada vez que se abre para un servicio, el formulario arranca limpio.
    useEffect(() => {
        if (servicio) {
            reset();
            clearErrors();
            setData('servicio', servicio.hashid);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [servicio?.hashid]);

    const guardar: FormEventHandler = (e) => {
        e.preventDefault();
        post('/tickets', { preserveScroll: true, preserveState: true, onSuccess: () => { reset(); onClose(); } });
    };

    return (
        <Dialog open={servicio !== null} onOpenChange={(abierto) => !abierto && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nuevo ticket de soporte</DialogTitle>
                    <DialogDescription>
                        Servicio {servicio?.codigo} — {servicio?.titular}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={guardar} className="space-y-4">
                    <InputError message={errors.servicio} />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="tipo_falla_id">Tipo de falla</Label>
                            <Select value={data.tipo_falla_id} onValueChange={(v) => setData('tipo_falla_id', v)}>
                                <SelectTrigger id="tipo_falla_id"><SelectValue placeholder="Selecciona" /></SelectTrigger>
                                <SelectContent>
                                    {tiposFalla.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>{t.nombre}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.tipo_falla_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="prioridad">Prioridad</Label>
                            <Select value={data.prioridad} onValueChange={(v) => setData('prioridad', v)}>
                                <SelectTrigger id="prioridad"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    {prioridades.map((p) => (
                                        <SelectItem key={p.value} value={p.value}>{p.label}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.prioridad} />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="descripcion">Descripción del problema</Label>
                        <textarea
                            id="descripcion"
                            rows={4}
                            value={data.descripcion}
                            onChange={(e) => setData('descripcion', e.target.value)}
                            className="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                            placeholder="Qué reporta el cliente, desde cuándo, qué se ha revisado..."
                        />
                        <InputError message={errors.descripcion} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="fecha_visita">Fecha de visita (opcional)</Label>
                        <Input id="fecha_visita" type="datetime-local" value={data.fecha_visita} onChange={(e) => setData('fecha_visita', e.target.value)} />
                        <InputError message={errors.fecha_visita} />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>Cancelar</Button>
                        <Button type="submit" disabled={processing}>Crear ticket</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
