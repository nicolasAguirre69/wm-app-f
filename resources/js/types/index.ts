import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    permissions: string[];
}

// --- Tipos de dominio ---

export interface Ciudad {
    id: number;
    isp_id: number;
    nombre: string;
    created_at: string;
    updated_at: string;
}

export interface Barrio {
    id: number;
    isp_id: number;
    ciudad_id: number;
    nombre: string;
    prefijo: string;
    ciudad?: Ciudad;
    redes_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Red {
    id: number;
    isp_id: number;
    barrio_id: number;
    numero: number;
    nombre: string; // Calculado en el backend: numero + prefijo del barrio.
    barrio?: Barrio;
    created_at: string;
    updated_at: string;
}

// Opción ligera para selectores (id + nombre).
export interface OpcionSelect {
    id: number;
    nombre: string;
}

// Opción de barrio para el selector de redes (incluye prefijo para preview).
export interface BarrioOption {
    id: number;
    nombre: string;
    prefijo: string;
}

// Estructura de una lista paginada de Laravel (paginate()).
// Genérica: Paginated<Ciudad>, Paginated<Barrio>, etc.
export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    // Si tiene sub-items, se renderiza como sección desplegable.
    items?: NavItem[];
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { success: string | null; error: string | null };
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    isp_id: number | null;
    is_super_admin: boolean;
    activo: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
