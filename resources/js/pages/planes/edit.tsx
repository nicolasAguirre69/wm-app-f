import { PlanFormFields } from '@/components/plan-form-fields';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type Plan, type TipoCatalogo } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

interface Props {
    plan: Plan;
    tiposPlan: TipoCatalogo[];
    tiposServicio: TipoCatalogo[];
}

export default function PlanEdit({ plan, tiposPlan, tiposServicio }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Planes', href: '/planes' },
        { title: 'Editar', href: `/planes/${plan.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        tipo_plan_id: String(plan.tipo_plan_id),
        tipo_servicio_id: String(plan.tipo_servicio_id),
        cantidad: plan.cantidad !== null ? String(plan.cantidad) : '',
        valor: plan.valor,
        activo: plan.activo,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        put(`/planes/${plan.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Editar plan" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Editar plan</h1>
                    <p className="text-muted-foreground text-sm">Modifica los datos del plan.</p>
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
                            Guardar cambios
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
