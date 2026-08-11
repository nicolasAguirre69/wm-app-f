<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Isp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $esSuper = $actor->is_super_admin;

        $paginador = User::query()
            ->where('is_super_admin', false) // el Super Admin no se lista aquí
            ->with('isp:id,nombre')
            // El admin de ISP solo ve los usuarios de su ISP.
            ->when(! $esSuper, fn ($q) => $q->where('isp_id', $actor->isp_id))
            // Filtro por ISP (solo Super Admin).
            ->when($esSuper && $request->filled('isp_id'), fn ($q) => $q->where('isp_id', $request->integer('isp_id')))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($sub) use ($request) {
                $s = '%'.$request->string('search').'%';
                $sub->where('name', 'like', $s)->orWhere('email', 'like', $s);
            }))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        // Rol de cada usuario de la página (en su ISP/team).
        $ids = collect($paginador->items())->pluck('id');
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereIn('model_has_roles.model_id', $ids)
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->pluck('roles.name', 'model_has_roles.model_id');

        $paginador->through(fn (User $u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'isp_id' => $u->isp_id,
            'isp_nombre' => $u->isp?->nombre,
            'activo' => $u->activo,
            'rol' => $roles[$u->id] ?? '—',
        ]);

        return Inertia::render('usuarios/index', [
            'usuarios' => $paginador,
            'filtros' => $request->only('search', 'isp_id'),
            'esSuperAdmin' => $esSuper,
            'isps' => $esSuper ? Isp::orderBy('nombre')->get(['id', 'nombre']) : null,
            'roles' => Role::query()->pluck('name')->unique()->values(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $actor = $request->user();
        // Super Admin elige el ISP; el admin de ISP usa el suyo.
        $ispId = $actor->is_super_admin ? (int) $request->validated('isp_id') : $actor->isp_id;

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'isp_id' => $ispId,
            'is_super_admin' => false,
            'activo' => $request->boolean('activo', true),
            'email_verified_at' => now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($ispId);
        $user->assignRole($request->validated('rol'));

        return redirect()->route('usuarios.index')->with('success', 'Usuario creado correctamente.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $datos = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'activo' => $request->boolean('activo'),
        ];

        // Solo cambia la contraseña si escribieron una nueva.
        if ($request->filled('password')) {
            $datos['password'] = $request->validated('password');
        }

        $user->update($datos);

        // Sincroniza el rol dentro del ISP (team) del usuario.
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->isp_id);
        $user->syncRoles([$request->validated('rol')]);

        return redirect()->route('usuarios.index')->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('usuarios.index')->with('success', 'Usuario eliminado correctamente.');
    }
}
