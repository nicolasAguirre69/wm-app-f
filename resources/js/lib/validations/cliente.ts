import { z } from 'zod';

// Mensaje reutilizable para campos obligatorios.
const requerido = (campo: string) => `${campo} es obligatorio.`;

// Teléfono: se ignoran espacios/guiones; deben quedar entre 7 y 11 dígitos.
const telefonoValido = (v: string) => /^\d{7,11}$/.test(v.replace(/\D/g, ''));

/**
 * Esquema de validación del formulario de cliente.
 *
 * @param esCreacion  Al crear en la ISP principal el documento es obligatorio;
 *                    al editar es opcional (se conserva el actual).
 * @param planAutomatico  ISP cliente sin planes propios: el plan (TV) lo asigna el sistema.
 * @param esIspCliente  ISP cliente: no se
 *                      pide documento.
 * @param modo  'completo' (cliente nuevo: persona + servicio), 'titular' (solo
 *              datos de la persona) o 'servicio' (solo el servicio).
 */
export function clienteSchema(
    esCreacion: boolean,
    esIspCliente = false,
    modo: 'completo' | 'titular' | 'servicio' = 'completo',
    planAutomatico = esIspCliente,
) {
    const documentoObligatorio = esCreacion && !esIspCliente;

    // Datos de la PERSONA (titular).
    const persona = {
        tipo_identificacion: z.string().min(1, requerido('El tipo de identificación')),
        identificacion: z.string().min(1, requerido('La identificación')),
        // Lo calcula el sistema según el tipo de identificación.
        tipo_contribuyente: z.string().optional(),

        primer_nombre: z.string().min(1, requerido('El primer nombre')),
        segundo_nombre: z.string().optional(),
        // Obligatorio salvo para NIT (empresa: la razón social va en primer_nombre).
        primer_apellido: z.string().optional(),
        segundo_apellido: z.string().optional(),

        telefono_1: z.string().min(1, requerido('El teléfono 1')).refine(telefonoValido, 'El teléfono 1 debe tener entre 7 y 11 dígitos.'),
        telefono_2: z.string().optional().refine((v) => !v || telefonoValido(v), 'El teléfono 2 debe tener entre 7 y 11 dígitos.'),
        // Correo opcional; si viene, debe ser válido.
        correo: z.string().optional().refine((v) => !v || /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v), 'El correo no es válido.'),
    };

    // Datos del SERVICIO.
    const servicio = {
        codigo_cliente: z.string().min(1, requerido('El código')),

        ciudad_id: z.string().min(1, requerido('La ciudad')),
        barrio_id: z.string().min(1, requerido('El barrio')),
        direccion: z.string().min(1, requerido('La dirección')),

        plan_id: planAutomatico ? z.string().optional() : z.string().min(1, requerido('El plan')),
        estado_id: z.string().min(1, requerido('El estado')),

        // Opcionales; si viene día de corte, entre 1 y 31.
        fecha_instalacion: z.string().optional(),
        // Solo ISP principal: el servicio va por un puerto alquilado a una ISP externa.
        puerto_alquilado: z.boolean().optional(),

        dia_corte: z.string().optional().refine((v) => !v || (Number(v) >= 1 && Number(v) <= 31), 'El día de corte debe estar entre 1 y 31.'),

        // Documento: obligatorio al crear en la ISP principal; si no, opcional.
        documento_digitalizado: documentoObligatorio
            ? z.instanceof(File, { message: requerido('El documento digitalizado') })
            : z.instanceof(File).nullable().optional(),
    };

    if (modo === 'servicio') {
        return z.object(servicio);
    }

    const campos = modo === 'titular' ? persona : { ...persona, ...servicio };

    return z.object(campos).superRefine((d, ctx) => {
        if (d.tipo_identificacion !== 'NIT' && !d.primer_apellido?.trim()) {
            ctx.addIssue({ code: 'custom', path: ['primer_apellido'], message: requerido('El primer apellido') });
        }
    });
}

export type ClienteFormData = z.infer<ReturnType<typeof clienteSchema>>;
