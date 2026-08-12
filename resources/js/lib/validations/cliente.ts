import { z } from 'zod';

// Mensaje reutilizable para campos obligatorios.
const requerido = (campo: string) => `${campo} es obligatorio.`;

/**
 * Esquema de validación del formulario de cliente.
 *
 * @param esCreacion  Reservado por si en el futuro cambian reglas según crear/editar.
 *                    El documento es opcional en ambos casos.
 */
export function clienteSchema(esCreacion: boolean) {
    void esCreacion;
    return z.object({
        codigo_cliente: z.string().min(1, requerido('El código')),
        tipo_identificacion: z.string().min(1, requerido('El tipo de identificación')),
        identificacion: z.string().min(1, requerido('La identificación')),
        tipo_contribuyente: z.string().min(1, requerido('El tipo de contribuyente')),

        primer_nombre: z.string().min(1, requerido('El primer nombre')),
        segundo_nombre: z.string().optional(),
        primer_apellido: z.string().min(1, requerido('El primer apellido')),
        segundo_apellido: z.string().optional(),

        telefono_1: z.string().min(1, requerido('El teléfono 1')),
        telefono_2: z.string().optional(),
        // Correo opcional; si viene, debe ser válido.
        correo: z.string().optional().refine((v) => !v || /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v), 'El correo no es válido.'),

        ciudad_id: z.string().min(1, requerido('La ciudad')),
        barrio_id: z.string().min(1, requerido('El barrio')),
        direccion: z.string().min(1, requerido('La dirección')),

        plan_id: z.string().min(1, requerido('El plan')),
        estado_id: z.string().min(1, requerido('El estado')),

        // Opcionales; si viene día de corte, entre 1 y 31.
        fecha_instalacion: z.string().optional(),
        dia_corte: z.string().optional().refine((v) => !v || (Number(v) >= 1 && Number(v) <= 31), 'El día de corte debe estar entre 1 y 31.'),

        // Documento opcional: si se sube, debe ser un archivo; si no, se omite.
        documento_digitalizado: z.instanceof(File).nullable().optional(),
    });
}

export type ClienteFormData = z.infer<ReturnType<typeof clienteSchema>>;
