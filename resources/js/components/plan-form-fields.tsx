import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { type TipoCatalogo } from '@/types';

export interface PlanFormData {
    tipo_plan_id: string;
    tipo_servicio_id: string;
    cantidad: string;
    valor: string;
    activo: boolean;
}

interface Props {
    data: PlanFormData;
    setData: (key: keyof PlanFormData, value: string | boolean) => void;
    errors: Partial<Record<keyof PlanFormData, string>>;
    tiposPlan: TipoCatalogo[];
    tiposServicio: TipoCatalogo[];
}

export function PlanFormFields({ data, setData, errors, tiposPlan, tiposServicio }: Props) {
    return (
        <>
            <div className="grid gap-2">
                <Label htmlFor="tipo_plan_id">Tipo de plan</Label>
                <Select value={data.tipo_plan_id} onValueChange={(value) => setData('tipo_plan_id', value)}>
                    <SelectTrigger id="tipo_plan_id">
                        <SelectValue placeholder="Selecciona un tipo de plan" />
                    </SelectTrigger>
                    <SelectContent>
                        {tiposPlan.map((tipo) => (
                            <SelectItem key={tipo.id} value={String(tipo.id)}>
                                {tipo.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.tipo_plan_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="tipo_servicio_id">Tipo de servicio</Label>
                <Select value={data.tipo_servicio_id} onValueChange={(value) => setData('tipo_servicio_id', value)}>
                    <SelectTrigger id="tipo_servicio_id">
                        <SelectValue placeholder="Selecciona un tipo de servicio" />
                    </SelectTrigger>
                    <SelectContent>
                        {tiposServicio.map((tipo) => (
                            <SelectItem key={tipo.id} value={String(tipo.id)}>
                                {tipo.nombre}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={errors.tipo_servicio_id} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="cantidad">Velocidad (Mbps)</Label>
                <Input
                    id="cantidad"
                    type="number"
                    min={1}
                    value={data.cantidad}
                    onChange={(e) => setData('cantidad', e.target.value)}
                    placeholder="Ej. 300 (deja vacío si es solo TV)"
                />
                <InputError message={errors.cantidad} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="valor">Valor (COP)</Label>
                <Input
                    id="valor"
                    type="number"
                    min={0}
                    step="0.01"
                    value={data.valor}
                    onChange={(e) => setData('valor', e.target.value)}
                    placeholder="Ej. 50000"
                />
                <InputError message={errors.valor} />
            </div>

            <div className="flex items-center gap-2">
                <Checkbox
                    id="activo"
                    checked={data.activo}
                    onCheckedChange={(checked) => setData('activo', checked === true)}
                />
                <Label htmlFor="activo">Plan activo</Label>
            </div>
        </>
    );
}
