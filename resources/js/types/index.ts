import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    permissions: string[];
    isp: { id: number; nombre: string } | null;
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

export interface TipoCatalogo {
    id: number;
    nombre: string;
    color?: string; // solo lo usan los estados de cliente
}

export interface Plan {
    id: number;
    isp_id: number;
    tipo_plan_id: number;
    tipo_servicio_id: number;
    cantidad: number | null;
    valor: string; // Laravel serializa decimal como string (ej. "50000.00").
    activo: boolean;
    tipo_plan?: TipoCatalogo;
    tipo_servicio?: TipoCatalogo;
    created_at: string;
    updated_at: string;
}

export interface Cliente {
    id: number;
    isp_id: number;
    codigo_cliente: string;
    tipo_identificacion: string;
    identificacion: string;
    tipo_contribuyente: string;
    primer_nombre: string;
    segundo_nombre: string | null;
    primer_apellido: string;
    segundo_apellido: string | null;
    telefono_1: string;
    telefono_2: string | null;
    correo: string | null;
    ciudad_id: number;
    barrio_id: number;
    direccion: string;
    plan_id: number;
    estado_id: number;
    fecha_instalacion: string | null;
    dia_corte: number | null;
    documento_digitalizado: string | null;
    facturable: boolean;
    motivo_no_facturable: string | null;
    isp?: TipoCatalogo;
    ciudad?: TipoCatalogo;
    barrio?: TipoCatalogo;
    plan?: { id: number; tipo_servicio?: TipoCatalogo };
    estado?: TipoCatalogo;
    created_at: string;
    updated_at: string;
}

// Opción {value,label} para selectores basados en enums de PHP.
export interface EnumOption {
    value: string;
    label: string;
}

// Barrio para selector encadenado (incluye ciudad_id e isp_id para filtrar).
export interface BarrioSelect {
    id: number;
    nombre: string;
    ciudad_id: number;
    isp_id: number;
}

// Opción de catálogo por ISP (plan, estado) para acotar por ISP del cliente.
export interface OpcionIsp {
    id: number;
    nombre: string;
    isp_id: number;
}

export interface Comentario {
    id: number;
    tipo: 'seguimiento' | 'facturacion';
    contenido: string;
    autor: string | null;
    fecha: string;
    puede_borrar: boolean;
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
