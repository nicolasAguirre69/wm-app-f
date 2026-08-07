import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Waypoints } from 'lucide-react';

export default function AppLogo() {
    const { auth } = usePage<SharedData>().props;
    const nombreIsp = auth.isp?.nombre ?? 'Web Master Colombia';

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                <Waypoints className="size-5" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="mb-0.5 truncate leading-none font-semibold">{nombreIsp}</span>
            </div>
        </>
    );
}
