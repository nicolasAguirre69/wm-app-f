import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { usePermissions } from '@/hooks/use-permissions';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Building2, LayoutGrid, MapPin, Package, Tag, UserCog, Users } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const { can } = usePermissions();
    const esSuperAdmin = auth.user?.is_super_admin ?? false;

    // Menú según el rol.
    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
        { title: 'Clientes', url: '/clientes', icon: Users },
    ];

    if (esSuperAdmin) {
        // El Super Admin administra la plataforma: ISPs y usuarios agrupados.
        mainNavItems.push({
            title: 'Administración',
            url: '#',
            icon: Building2,
            items: [
                { title: 'ISPs', url: '/isps' },
                { title: 'Usuarios', url: '/usuarios' },
                { title: 'Ciudades', url: '/ciudades' },
            ],
        });
    } else {
        // El usuario de ISP administra sus propios catálogos.
        mainNavItems.push({
            title: 'Ubicaciones',
            url: '#',
            icon: MapPin,
            items: [
                { title: 'Barrios', url: '/barrios' },
                { title: 'Redes', url: '/redes' },
            ],
        });
        mainNavItems.push({ title: 'Planes', url: '/planes', icon: Package });
        mainNavItems.push({ title: 'Estados de cliente', url: '/estados', icon: Tag });

        // Usuarios: solo para Administradores de ISP (con permiso).
        if (can('usuarios.ver')) {
            mainNavItems.push({ title: 'Usuarios', url: '/usuarios', icon: UserCog });
        }
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
