import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Ciudades', href: '/ciudades' },
    { title: 'Nueva', href: '/ciudades/create' },
];

export default function CiudadCreate() {
    // useForm: estado del formulario + envío + errores del backend, todo junto.
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/ciudades');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nueva ciudad" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Nueva ciudad</h1>
                    <p className="text-muted-foreground text-sm">Registra una ciudad para tu ISP.</p>
                </div>

                <form onSubmit={submit} className="max-w-md space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input
                            id="nombre"
                            value={data.nombre}
                            onChange={(e) => setData('nombre', e.target.value)}
                            autoFocus
                            placeholder="Ej. Bogotá"
                        />
                        <InputError message={errors.nombre} />
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            Guardar
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
