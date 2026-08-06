import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Map, MapPin, Package, Tag, Users } from 'lucide-react';
import AppLogo from './app-logo';

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        url: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        url: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const esSuperAdmin = auth.user?.is_super_admin ?? false;

    // Menú según el rol.
    const mainNavItems: NavItem[] = [
        { title: 'Dashboard', url: '/dashboard', icon: LayoutGrid },
        { title: 'Clientes', url: '/clientes', icon: Users },
    ];

    if (esSuperAdmin) {
        // El Super Admin administra la configuración global (ciudades) y no
        // los catálogos operativos de cada ISP.
        mainNavItems.push({ title: 'Ciudades', url: '/ciudades', icon: MapPin });
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
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
