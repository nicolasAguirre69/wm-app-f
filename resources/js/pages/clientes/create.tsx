import { ClienteFormFields, type ClienteFormValues } from '@/components/cliente-form-fields';
import { Button } from '@/components/ui/button';
import { clienteSchema } from '@/lib/validations/cliente';
import AppLayout from '@/layouts/app-layout';
import { type BarrioSelect, type BreadcrumbItem, type EnumOption, type OpcionSelect } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Clientes', href: '/clientes' },
    { title: 'Nuevo', href: '/clientes/create' },
];

interface Props {
    ciudades: OpcionSelect[];
    barrios: BarrioSelect[];
    planes: OpcionSelect[];
    estados: OpcionSelect[];
    tiposIdentificacion: EnumOption[];
    tiposContribuyente: EnumOption[];
}

const valoresIniciales: ClienteFormValues = {
    codigo_cliente: '',
    tipo_identificacion: '',
    identificacion: '',
    tipo_contribuyente: '',
    primer_nombre: '',
    segundo_nombre: '',
    primer_apellido: '',
    segundo_apellido: '',
    telefono_1: '',
    telefono_2: '',
    correo: '',
    ciudad_id: '',
    barrio_id: '',
    direccion: '',
    plan_id: '',
    estado_id: '',
    fecha_instalacion: '',
    dia_corte: '',
    documento_digitalizado: null,
};

export default function ClienteCreate({ ciudades, barrios, planes, estados, tiposIdentificacion, tiposContribuyente }: Props) {
    const { data, setData, post, processing, errors } = useForm<ClienteFormValues>(valoresIniciales);
    // Errores de la validación de cliente (Zod).
    const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // 1. Validación en el cliente con Zod.
        const result = clienteSchema(true).safeParse(data);
        if (!result.success) {
            const errs: Record<string, string> = {};
            result.error.issues.forEach((issue) => {
                errs[String(issue.path[0])] = issue.message;
            });
            setClientErrors(errs);
            return;
        }
        setClientErrors({});

        // 2. Envío (Inertia detecta el File y usa multipart automáticamente).
        post('/clientes');
    };

    const mergedErrors = { ...errors, ...clientErrors };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo cliente" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Nuevo cliente</h1>
                    <p className="text-muted-foreground text-sm">Registra un cliente para tu ISP.</p>
                </div>

                <form onSubmit={submit} className="max-w-3xl space-y-8">
                    <ClienteFormFields
                        data={data}
                        setData={setData}
                        errors={mergedErrors}
                        ciudades={ciudades}
                        barrios={barrios}
                        planes={planes}
                        estados={estados}
                        tiposIdentificacion={tiposIdentificacion}
                        tiposContribuyente={tiposContribuyente}
                    />

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            Guardar cliente
                        </Button>
                        <Button type="button" variant="ghost" asChild>
                            <Link href="/clientes">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
