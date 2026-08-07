<?php

namespace App\Http\Controllers;

use App\Enums\TipoComentario;
use App\Models\Cliente;
use App\Models\Comentario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ComentarioController extends Controller
{
    /**
     * Crea un comentario para un cliente.
     */
    public function store(Request $request, Cliente $cliente): RedirectResponse
    {
        // Debe poder ver el cliente (Policy de Cliente).
        $this->authorize('view', $cliente);

        $datos = $request->validate([
            'tipo' => ['required', Rule::enum(TipoComentario::class)],
            'contenido' => ['required', 'string', 'max:2000'],
        ]);

        // Los comentarios de facturación solo los crea Super Admin o rol Facturación.
        if ($datos['tipo'] === TipoComentario::Facturacion->value && ! $request->user()->puedeVerFacturacion()) {
            abort(403);
        }

        $cliente->comentarios()->create([
            'isp_id' => $cliente->isp_id,
            'user_id' => $request->user()->id,
            'tipo' => $datos['tipo'],
            'contenido' => $datos['contenido'],
        ]);

        return back()->with('success', 'Comentario agregado.');
    }

    /**
     * Edita un comentario. Solo el autor o el Super Admin.
     */
    public function update(Request $request, Comentario $comentario): RedirectResponse
    {
        $user = $request->user();

        if (! $user->is_super_admin && $comentario->user_id !== $user->id) {
            abort(403);
        }

        $datos = $request->validate([
            'contenido' => ['required', 'string', 'max:2000'],
        ]);

        $comentario->update(['contenido' => $datos['contenido']]);

        return back()->with('success', 'Comentario actualizado.');
    }

    /**
     * Elimina un comentario. Solo el autor o el Super Admin.
     */
    public function destroy(Request $request, Comentario $comentario): RedirectResponse
    {
        $user = $request->user();

        if (! $user->is_super_admin && $comentario->user_id !== $user->id) {
            abort(403);
        }

        $comentario->delete();

        return back()->with('success', 'Comentario eliminado.');
    }
}
