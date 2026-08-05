import { PlanFormFields } from '@/components/plan-form-fields';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type TipoCatalogo } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Planes', href: '/planes' },
    { title: 'Nuevo', href: '/planes/create' },
];

interface Props {
    tiposPlan: TipoCatalogo[];
    tiposServicio: TipoCatalogo[];
}

export default function PlanCreate({ tiposPlan, tiposServicio }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        tipo_plan_id: '',
        tipo_servicio_id: '',
        cantidad: '',
        valor: '',
        activo: true,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/planes');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo plan" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Nuevo plan</h1>
                    <p className="text-muted-foreground text-sm">Registra un plan para tu ISP.</p>
                </div>

                <form onSubmit={submit} className="max-w-md space-y-6">
                    <PlanFormFields
                        data={data}
                        setData={setData}
                        errors={errors}
                        tiposPlan={tiposPlan}
                        tiposServicio={tiposServicio}
                    />

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            Guardar
                        </Button>
                        <Button type="button" variant="ghost" asChild>
                            <Link href="/planes">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
