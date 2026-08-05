import { ClienteFormFields, type ClienteFormValues } from '@/components/cliente-form-fields';
import { Button } from '@/components/ui/button';
import { clienteSchema } from '@/lib/validations/cliente';
import AppLayout from '@/layouts/app-layout';
import { type BarrioSelect, type BreadcrumbItem, type Cliente, type EnumOption, type OpcionSelect } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Props {
    cliente: Cliente;
    ciudades: OpcionSelect[];
    barrios: BarrioSelect[];
    planes: OpcionSelect[];
    estados: OpcionSelect[];
    tiposIdentificacion: EnumOption[];
    tiposContribuyente: EnumOption[];
}

export default function ClienteEdit({ cliente, ciudades, barrios, planes, estados, tiposIdentificacion, tiposContribuyente }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Clientes', href: '/clientes' },
        { title: cliente.codigo_cliente, href: `/clientes/${cliente.id}/edit` },
    ];

    const { data, setData, post, processing, errors } = useForm<ClienteFormValues & { _method: string }>({
        _method: 'put', // Method spoofing: necesario para subir archivos con PUT.
        codigo_cliente: cliente.codigo_cliente,
        tipo_identificacion: cliente.tipo_identificacion,
        identificacion: cliente.identificacion,
        tipo_contribuyente: cliente.tipo_contribuyente,
        primer_nombre: cliente.primer_nombre,
        segundo_nombre: cliente.segundo_nombre ?? '',
        primer_apellido: cliente.primer_apellido,
        segundo_apellido: cliente.segundo_apellido,
        telefono_1: cliente.telefono_1,
        telefono_2: cliente.telefono_2 ?? '',
        correo: cliente.correo,
        ciudad_id: String(cliente.ciudad_id),
        barrio_id: String(cliente.barrio_id),
        direccion: cliente.direccion,
        plan_id: String(cliente.plan_id),
        estado_id: String(cliente.estado_id),
        fecha_instalacion: cliente.fecha_instalacion.slice(0, 10),
        dia_corte: String(cliente.dia_corte),
        documento_digitalizado: null,
    });

    const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const result = clienteSchema(false).safeParse(data);
        if (!result.success) {
            const errs: Record<string, string> = {};
            result.error.issues.forEach((issue) => {
                errs[String(issue.path[0])] = issue.message;
            });
            setClientErrors(errs);
            return;
        }
        setClientErrors({});

        post(`/clientes/${cliente.id}`);
    };

    const mergedErrors = { ...errors, ...clientErrors };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${cliente.codigo_cliente}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Editar cliente</h1>
                    <p className="text-muted-foreground text-sm">Modifica los datos del cliente.</p>
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
                        documentoActual={cliente.documento_digitalizado}
                    />

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            Guardar cambios
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
