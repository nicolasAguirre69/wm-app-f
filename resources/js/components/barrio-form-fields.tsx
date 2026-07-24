import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type OpcionSelect } from '@/types';

// Forma de los datos del formulario de barrio.
export interface BarrioFormData {
    ciudad_id: string;
    nombre: string;
    prefijo: string;
}

interface Props {
    data: BarrioFormData;
    setData: (key: keyof BarrioFormData, value: string) => void;
    errors: Partial<Record<keyof BarrioFormData, string>>;
    ciudades: OpcionSelect[];
}

/**
 * Campos compartidos por los formularios de crear y editar barrio.
 * Recibe los helpers de useForm como props para no duplicar el markup.
 */
export function BarrioFormFields({ data, setData, errors, ciudades }: Props) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="ciudad_id">Ciudad</Label>
                <Select value={data.ciudad_id} onValueChange={(value) => setData('ciudad_id', value)}>
                    <SelectTrigger id="ciudad_id">
                        <SelectValue placeholder="Selecciona una ciudad" />
                    </SelectTrigger>
                    <SelectContent>
                        {ciudades.map((ciudad) => (
                            <SelectItem key={ciudad.id} value={String(ciudad.id)}>
                                {ciudad.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.ciudad_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                    id="nombre"
                    value={data.nombre}
                    onChange={(e) => setData('nombre', e.target.value)}
                    placeholder="Ej. Usme"
                />
                <InputError message={errors.nombre} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="prefijo">Prefijo</Label>
                <Input
                    id="prefijo"
                    value={data.prefijo}
                    onChange={(e) => setData('prefijo', e.target.value)}
                    placeholder="Ej. USM"
                />
                <InputError message={errors.prefijo} />
            </div>
        </>
    );
}
