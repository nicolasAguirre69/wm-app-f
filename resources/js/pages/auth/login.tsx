import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Waypoints } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

interface LoginProps {
    status?: string;
}

export default function Login({ status }: LoginProps) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Iniciar sesión" />

            <div className="flex min-h-svh bg-[#F3F4F6] text-[#111827] dark:bg-neutral-950 dark:text-neutral-100">
                {/* Panel de marca (solo escritorio) */}
                <div className="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-[#1E3A8A] p-12 text-white lg:flex">
                    {/* Detalle decorativo sutil */}
                    <div className="pointer-events-none absolute -right-24 -top-24 size-96 rounded-full bg-white/5" />
                    <div className="pointer-events-none absolute -bottom-32 -left-16 size-96 rounded-full bg-white/5" />

                    <div className="relative flex items-center gap-3">
                        <div className="flex size-10 items-center justify-center rounded-xl bg-white/10 ring-1 ring-white/20">
                            <Waypoints className="size-5" />
                        </div>
                        <span className="text-lg font-semibold tracking-tight">Web Master Colombia</span>
                    </div>

                    <div className="relative space-y-4">
                        <h1 className="text-4xl font-bold leading-tight tracking-tight">
                            Administración de usuarios de TV
                        </h1>
                        <p className="max-w-md text-lg text-white/75">
                            de los ISP de Web Master Colombia.
                        </p>
                    </div>

                    <p className="relative text-sm text-white/50">
                        &copy; {new Date().getFullYear()}{' '}
                        <a
                            href="https://webmastercolombia.net"
                            target="_blank"
                            rel="noreferrer"
                            className="font-medium text-white/70 underline underline-offset-2 hover:text-white"
                        >
                            Web Master Colombia
                        </a>
                        . Todos los derechos reservados.
                    </p>
                </div>

                {/* Formulario */}
                <div className="flex w-full items-center justify-center px-6 py-10 sm:px-10 lg:w-1/2">
                    <div className="w-full max-w-sm">
                        {/* Marca en móvil */}
                        <div className="mb-8 flex items-center gap-3 lg:hidden">
                            <div className="flex size-10 items-center justify-center rounded-xl bg-[#1E3A8A] text-white">
                                <Waypoints className="size-5" />
                            </div>
                            <span className="text-lg font-semibold tracking-tight">Web Master Colombia</span>
                        </div>

                        <div className="mb-8">
                            <h2 className="text-2xl font-bold tracking-tight">Iniciar sesión</h2>
                            <p className="text-[#6B7280] dark:text-neutral-400">Ingresa tus credenciales para continuar.</p>
                        </div>

                        {status && (
                            <div className="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm font-medium text-green-700">
                                {status}
                            </div>
                        )}

                        <form className="flex flex-col gap-5" onSubmit={submit}>
                            <div className="grid gap-2">
                                <Label htmlFor="email">Correo electrónico</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="correo@ejemplo.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Contraseña</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="••••••••"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="remember"
                                    checked={data.remember}
                                    onCheckedChange={(checked) => setData('remember', checked === true)}
                                    tabIndex={3}
                                />
                                <Label htmlFor="remember" className="text-sm font-normal">Mantener sesión iniciada</Label>
                            </div>

                            <Button
                                type="submit"
                                className="mt-2 w-full bg-[#1E3A8A] text-white hover:bg-[#1E3A8A]/90"
                                tabIndex={4}
                                disabled={processing}
                            >
                                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                Ingresar
                            </Button>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}
