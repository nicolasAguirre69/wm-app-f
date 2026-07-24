import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Ciudad } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    ciudad: Ciudad;
}

export default function CiudadEdit({ ciudad }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Ciudades', href: '/ciudades' },
        { title: ciudad.nombre, href: `/ciudades/${ciudad.id}/edit` },
    ];

    // Precargamos el formulario con los valores actuales de la ciudad.
    const { data, setData, put, processing, errors } = useForm({
        nombre: ciudad.nombre,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/ciudades/${ciudad.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${ciudad.nombre}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Editar ciudad</h1>
                    <p className="text-muted-foreground text-sm">Modifica los datos de la ciudad.</p>
                </div>

                <form onSubmit={submit} className="max-w-md space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input
                            id="nombre"
                            value={data.nombre}
                            onChange={(e) => setData('nombre', e.target.value)}
                            autoFocus
                        />
                        <InputError message={errors.nombre} />
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            Guardar cambios
                        </Button>
                        <Button type="button" variant="ghost" asChild>
                            <Link href="/ciudades">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
