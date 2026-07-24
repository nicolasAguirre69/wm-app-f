import { BarrioFormFields } from '@/components/barrio-form-fields';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type Barrio, type BreadcrumbItem, type OpcionSelect } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    barrio: Barrio;
    ciudades: OpcionSelect[];
}

export default function BarrioEdit({ barrio, ciudades }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Barrios', href: '/barrios' },
        { title: barrio.nombre, href: `/barrios/${barrio.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        ciudad_id: String(barrio.ciudad_id),
        nombre: barrio.nombre,
        prefijo: barrio.prefijo,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/barrios/${barrio.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${barrio.nombre}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Editar barrio</h1>
                    <p className="text-muted-foreground text-sm">Modifica los datos del barrio.</p>
                </div>

                <form onSubmit={submit} className="max-w-md space-y-6">
                    <BarrioFormFields data={data} setData={setData} errors={errors} ciudades={ciudades} />

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            Guardar cambios
                        </Button>
                        <Button type="button" variant="ghost" asChild>
                            <Link href="/barrios">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
