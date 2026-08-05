import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Estados de cliente', href: '/estados' },
    { title: 'Nuevo', href: '/estados/create' },
];

const COLORES = ['#22c55e', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#6b7280'];

export default function EstadoCreate() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        color: '#22c55e',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/estados');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nuevo estado" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-xl font-semibold">Nuevo estado</h1>
                    <p className="text-muted-foreground text-sm">Registra un estado de cliente para tu ISP.</p>
                </div>

                <form onSubmit={submit} className="max-w-md space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="nombre">Nombre</Label>
                        <Input
                            id="nombre"
                            value={data.nombre}
                            onChange={(e) => setData('nombre', e.target.value)}
                            autoFocus
                            placeholder="Ej. Activo"
                        />
                        <InputError message={errors.nombre} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="color">Color</Label>
                        <div className="flex items-center gap-2">
                            <input
                                id="color"
                                type="color"
                                value={data.color}
                                onChange={(e) => setData('color', e.target.value)}
                                className="h-9 w-14 cursor-pointer rounded border"
                            />
                            <div className="flex gap-1">
                                {COLORES.map((c) => (
                                    <button
                                        key={c}
                                        type="button"
                                        onClick={() => setData('color', c)}
                                        style={{ backgroundColor: c }}
                                        className={`size-6 rounded-full border-2 ${data.color === c ? 'border-foreground' : 'border-transparent'}`}
                                        aria-label={c}
                                    />
                                ))}
                            </div>
                        </div>
                        <InputError message={errors.color} />
                    </div>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={processing}>
                            Guardar
                        </Button>
                        <Button type="button" variant="ghost" asChild>
                            <Link href="/estados">Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
