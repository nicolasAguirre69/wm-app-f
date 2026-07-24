import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { type BarrioOption, type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

// Números de red disponibles: siempre del 1 al 16.
const NUMEROS = Array.from({ length: 16 }, (_, i) => i + 1);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Redes', href: '/redes' },
    { title: 'Nueva', href: '/redes/create' },
];

interface Props {
    barrios: BarrioOption[];
}

export default function RedCreate({ barrios }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        barrio_id: '',
        numero: '',
    });

    // Prefijo del barrio seleccionado, para previsualizar el nombre completo.
    const barrioSeleccionado = barrios.find((b) => String(b.id) === data.barrio_id);
    const preview = data.numero && barrioSeleccionado ? `${data.numero}${barrioSeleccionado.prefijo}` : null;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/redes');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva red" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Nueva red</h1>
                    <p className="text-muted-foreground text-sm">
                        Elige el barrio y escribe solo el número; el prefijo se agrega automáticamente.
                    </p>
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
                            Guardar
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
