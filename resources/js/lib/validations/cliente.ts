import { z } from 'zod';

// Mensaje reutilizable para campos obligatorios.
const requerido = (campo: string) => `${campo} es obligatorio.`;

/**
 * Esquema de validación del formulario de cliente.
 *
 * @param esCreacion  En creación el documento es obligatorio; al editar no
 *                    (si no se sube uno nuevo, se conserva el actual).
 */
export function clienteSchema(esCreacion: boolean) {
    return z.object({
        codigo_cliente: z.string().min(1, requerido('El código')),
        tipo_identificacion: z.string().min(1, requerido('El tipo de identificación')),
        identificacion: z.string().min(1, requerido('La identificación')),
        tipo_contribuyente: z.string().min(1, requerido('El tipo de contribuyente')),

        primer_nombre: z.string().min(1, requerido('El primer nombre')),
        segundo_nombre: z.string().optional(),
        primer_apellido: z.string().min(1, requerido('El primer apellido')),
        segundo_apellido: z.string().min(1, requerido('El segundo apellido')),

        telefono_1: z.string().min(1, requerido('El teléfono 1')),
        telefono_2: z.string().optional(),
        correo: z.string().min(1, requerido('El correo')).email('El correo no es válido.'),

        ciudad_id: z.string().min(1, requerido('La ciudad')),
        barrio_id: z.string().min(1, requerido('El barrio')),
        direccion: z.string().min(1, requerido('La dirección')),

        plan_id: z.string().min(1, requerido('El plan')),
        estado_id: z.string().min(1, requerido('El estado')),

        fecha_instalacion: z.string().min(1, requerido('La fecha de instalación')),
        dia_corte: z
            .string()
            .min(1, requerido('El día de corte'))
            .refine((v) => Number(v) >= 1 && Number(v) <= 31, 'El día de corte debe estar entre 1 y 31.'),

        documento_digitalizado: esCreacion
            ? z.instanceof(File, { message: 'Debes adjuntar el documento.' })
            : z.instanceof(File).nullable().optional(),
    });
}

export type ClienteFormData = z.infer<ReturnType<typeof clienteSchema>>;
