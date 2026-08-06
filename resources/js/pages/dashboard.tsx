import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { UserPlus, Users } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface EstadoStat {
    nombre: string;
    color: string;
    total: number;
}

interface PlanStat {
    nombre: string;
    total: number;
}

interface Props {
    totalClientes: number;
    nuevosEsteMes: number;
    porEstado: EstadoStat[];
    porPlan: PlanStat[];
}

export default function Dashboard({ totalClientes, nuevosEsteMes, porEstado, porPlan }: Props) {
    // Valor máximo para escalar las barras (evita dividir por cero).
    const maxPlan = Math.max(1, ...porPlan.map((p) => p.total));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Tarjetas de resumen */}
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Total de clientes</CardTitle>
                            <Users className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{totalClientes}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">Nuevos este mes</CardTitle>
                            <UserPlus className="text-muted-foreground size-4" />
                        </CardHeader>
                        <CardContent>
                            <p className="text-3xl font-bold">{nuevosEsteMes}</p>
                        </CardContent>
                    </Card>

                    {/* Una tarjeta por estado, con su color */}
                    {porEstado.map((estado) => (
                        <Card key={estado.nombre}>
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">{estado.nombre}</CardTitle>
                                <span className="size-3 rounded-full border" style={{ backgroundColor: estado.color }} />
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-bold">{estado.total}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Distribución por plan (barras CSS) */}
                <Card>
                    <CardHeader>
                        <CardTitle>Distribución por plan</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {porPlan.length === 0 ? (
                            <p className="text-muted-foreground text-sm">No hay planes con clientes todavía.</p>
                        ) : (
                            porPlan.map((plan) => (
                                <div key={plan.nombre} className="space-y-1">
                                    <div className="flex items-center justify-between text-sm">
                                        <span>{plan.nombre}</span>
                                        <span className="text-muted-foreground font-medium">{plan.total}</span>
                                    </div>
                                    <div className="bg-muted h-2.5 w-full overflow-hidden rounded-full">
                                        <div
                                            className="bg-primary h-full rounded-full transition-all"
                                            style={{ width: `${(plan.total / maxPlan) * 100}%` }}
                                        />
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
