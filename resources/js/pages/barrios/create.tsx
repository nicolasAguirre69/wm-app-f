import { BarrioFormFields } from '@/components/barrio-form-fields';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type OpcionSelect } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Barrios', href: '/barrios' },
    { title: 'Nuevo', href: '/barrios/create' },
];

interface Props {
    ciudades: OpcionSelect[];
}

export default function BarrioCreate({ ciudades }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        ciudad_id: '',
        nombre: '',
        prefijo: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/barrios');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo barrio" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Nuevo barrio</h1>
                    <p className="text-muted-foreground text-sm">Registra un barrio para tu ISP.</p>
                </div>

                <form onSubmit={submit} className="max-w-md space-y-6">
                    <BarrioFormFields data={data} setData={setData} errors={errors} ciudades={ciudades} />

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            Guardar
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
