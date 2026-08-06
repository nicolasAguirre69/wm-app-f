import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Building2, TrendingUp, UserPlus, Users } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

interface EstadoStat {
    nombre: string;
    color: string;
    total: number;
}
interface NombreTotal {
    nombre: string;
    total: number;
}
interface MesStat {
    mes: string;
    total: number;
}

interface Props {
    esGlobal: boolean;
    // ISP
    totalClientes?: number;
    nuevosEsteMes?: number;
    porEstado?: EstadoStat[];
    porPlan?: NombreTotal[];
    // Global
    totalIsps?: number;
    porIsp?: NombreTotal[];
    porEstadoGlobal?: EstadoStat[];
    desglosePorEstado?: Record<string, NombreTotal[]>;
    crecimiento?: MesStat[];
}

// Tarjeta de métrica simple.
function StatCard({ titulo, valor, children }: { titulo: string; valor: number; children: React.ReactNode }) {
    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between pb-2">
                <CardTitle className="text-sm font-medium">{titulo}</CardTitle>
                {children}
            </CardHeader>
            <CardContent>
                <p className="text-3xl font-bold">{valor}</p>
            </CardContent>
        </Card>
    );
}

// Barras horizontales reutilizables.
function Barras({ datos }: { datos: NombreTotal[] }) {
    const max = Math.max(1, ...datos.map((d) => d.total));
    if (datos.length === 0) return <p className="text-muted-foreground text-sm">Sin datos todavía.</p>;
    return (
        <div className="space-y-3">
            {datos.map((d) => (
                <div key={d.nombre} className="space-y-1">
                    <div className="flex items-center justify-between text-sm">
                        <span>{d.nombre}</span>
                        <span className="text-muted-foreground font-medium">{d.total}</span>
                    </div>
                    <div className="bg-muted h-2.5 w-full overflow-hidden rounded-full">
                        <div className="bg-primary h-full rounded-full transition-all" style={{ width: `${(d.total / max) * 100}%` }} />
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function Dashboard(props: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            {props.esGlobal ? <DashboardGlobal {...props} /> : <DashboardIsp {...props} />}
        </AppLayout>
    );
}

// --- Vista del Super Admin (global) ---
function DashboardGlobal({ totalIsps = 0, totalClientes = 0, nuevosEsteMes = 0, porIsp = [], porEstadoGlobal = [], desglosePorEstado = {}, crecimiento = [] }: Props) {
    const maxMes = Math.max(1, ...crecimiento.map((m) => m.total));
    // Estado seleccionado para el modal de desglose por ISP.
    const [estadoSel, setEstadoSel] = useState<EstadoStat | null>(null);

    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <StatCard titulo="Total de ISPs" valor={totalIsps}><Building2 className="text-muted-foreground size-4" /></StatCard>
                <StatCard titulo="Clientes en la plataforma" valor={totalClientes}><Users className="text-muted-foreground size-4" /></StatCard>
                <StatCard titulo="Nuevos este mes" valor={nuevosEsteMes}><UserPlus className="text-muted-foreground size-4" /></StatCard>
            </div>

            {/* Clientes por estado (global). Clic → desglose por ISP en un modal. */}
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {porEstadoGlobal.map((estado) => (
                    <button key={estado.nombre} type="button" onClick={() => setEstadoSel(estado)} className="text-left">
                        <Card className="transition hover:border-foreground/30 hover:shadow-sm">
                            <CardHeader className="flex flex-row items-center justify-between pb-2">
                                <CardTitle className="text-sm font-medium">{estado.nombre}</CardTitle>
                                <span className="size-3 rounded-full border" style={{ backgroundColor: estado.color }} />
                            </CardHeader>
                            <CardContent>
                                <p className="text-3xl font-bold">{estado.total}</p>
                                <p className="text-muted-foreground text-xs">Ver desglose por ISP</p>
                            </CardContent>
                        </Card>
                    </button>
                ))}
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Clientes por ISP</CardTitle></CardHeader>
                    <CardContent><Barras datos={porIsp} /></CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex flex-row items-center justify-between">
                        <CardTitle>Crecimiento mensual</CardTitle>
                        <TrendingUp className="text-muted-foreground size-4" />
                    </CardHeader>
                    <CardContent>
                        {/* Gráfico de columnas simple con CSS */}
                        <div className="flex h-40 items-end gap-2">
                            {crecimiento.map((m) => (
                                <div key={m.mes} className="flex flex-1 flex-col items-center gap-1">
                                    <span className="text-muted-foreground text-xs">{m.total}</span>
                                    <div className="bg-primary w-full rounded-t transition-all" style={{ height: `${(m.total / maxMes) * 100}%`, minHeight: m.total > 0 ? '4px' : '0' }} />
                                    <span className="text-muted-foreground text-[10px]">{m.mes}</span>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Modal: desglose por ISP del estado seleccionado */}
            <Dialog open={estadoSel !== null} onOpenChange={(o) => !o && setEstadoSel(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            <span className="flex items-center gap-2">
                                {estadoSel && <span className="size-3 rounded-full border" style={{ backgroundColor: estadoSel.color }} />}
                                {estadoSel?.nombre} por ISP
                            </span>
                        </DialogTitle>
                    </DialogHeader>
                    {estadoSel && <Barras datos={desglosePorEstado[estadoSel.nombre] ?? []} />}
                </DialogContent>
            </Dialog>
        </div>
    );
}

// --- Vista del usuario ISP ---
function DashboardIsp({ totalClientes = 0, nuevosEsteMes = 0, porEstado = [], porPlan = [] }: Props) {
    return (
        <div className="flex h-full flex-1 flex-col gap-6 p-4">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard titulo="Total de clientes" valor={totalClientes}><Users className="text-muted-foreground size-4" /></StatCard>
                <StatCard titulo="Nuevos este mes" valor={nuevosEsteMes}><UserPlus className="text-muted-foreground size-4" /></StatCard>
                {porEstado.map((estado) => (
                    <Card key={estado.nombre}>
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-medium">{estado.nombre}</CardTitle>
                            <span className="size-3 rounded-full border" style={{ backgroundColor: estado.color }} />
                        </CardHeader>
                        <CardContent><p className="text-3xl font-bold">{estado.total}</p></CardContent>
                    </Card>
                ))}
            </div>

            <Card>
                <CardHeader><CardTitle>Distribución por plan</CardTitle></CardHeader>
                <CardContent><Barras datos={porPlan} /></CardContent>
            </Card>
        </div>
    );
}
