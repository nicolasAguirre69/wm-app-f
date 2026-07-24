import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Hook para verificar permisos del usuario en el frontend.
 *
 * El Super Admin siempre puede todo (bypass), igual que en el backend con
 * Gate::before. Para los demás, comprobamos su lista de permisos compartida
 * por Inertia.
 *
 * Uso:  const { can } = usePermissions();
 *       {can('ciudades.crear') && <Button>...</Button>}
 */
export function usePermissions() {
    const { auth } = usePage<SharedData>().props;

    const can = (permission: string): boolean => {
        if (auth.user?.is_super_admin) {
            return true;
        }
        return auth.permissions?.includes(permission) ?? false;
    };

    return { can };
}
