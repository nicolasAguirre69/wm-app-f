import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BarrioOption, type BreadcrumbItem, type Red } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const NUMEROS = Array.from({ length: 16 }, (_, i) => i + 1);

interface Props {
    red: Red;
    barrios: BarrioOption[];
}

export default function RedEdit({ red, barrios }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Redes', href: '/redes' },
        { title: red.nombre, href: `/redes/${red.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        barrio_id: String(red.barrio_id),
        numero: String(red.numero),
    });

    const barrioSeleccionado = barrios.find((b) => String(b.id) === data.barrio_id);
    const preview = data.numero && barrioSeleccionado ? `${data.numero}${barrioSeleccionado.prefijo}` : null;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/redes/${red.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${red.nombre}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Editar red</h1>
                    <p className="text-muted-foreground text-sm">Modifica los datos de la red.</p>
                </div>

                <form onSubmit={submit} className="max-w-md space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="barrio_id">Barrio</Label>
                        <Select value={data.barrio_id} onValueChange={(value) => setData('barrio_id', value)}>
                            <SelectTrigger id="barrio_id">
                                <SelectValue placeholder="Selecciona un barrio" />
                            </SelectTrigger>
                            <SelectContent>
                                {barrios.map((barrio) => (
                                    <SelectItem key={barrio.id} value={String(barrio.id)}>
                                        {barrio.nombre}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.barrio_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="numero">Número (1-16)</Label>
                        <Select value={data.numero} onValueChange={(value) => setData('numero', value)}>
                            <SelectTrigger id="numero">
                                <SelectValue placeholder="Selecciona un número" />
                            </SelectTrigger>
                            <SelectContent>
                                {NUMEROS.map((n) => (
                                    <SelectItem key={n} value={String(n)}>
                                        {n}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {preview && (
                            <p className="text-muted-foreground text-sm">
                                Nombre completo: <span className="text-foreground font-medium">{preview}</span>
                            </p>
                        )}
                        <InputError message={errors.numero} />
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            Guardar cambios
                        </Button>
                        <Button type="button" variant="ghost" asChild>
                            <Link href="/redes">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
