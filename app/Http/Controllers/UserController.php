<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\Casino;
use App\Models\Sucursal;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $req)
    {
        $user = $req->user();
        $currentRole = $user->roles->first(); // El primer rol asignado

        // ✅ Si es master_admin, ve todos los roles
        if ($currentRole->name === 'master_admin') {
            $roles = Role::orderBy('id')->get();
        } else {
            // 🚫 Si no es master_admin, solo puede ver roles con id >= al suyo
            $roles = Role::where('id', '>', $currentRole->id)
                ->orderBy('id')
                ->get();
        }

        // ✅ Filtro de usuarios según el rol y casino del usuario actual
        $usuarios = User::with('roles', 'casino', 'sucursal')
            ->when($currentRole->name !== 'master_admin', function ($query) use ($user) {
                // Si no es master_admin, solo usuarios del mismo casino
                $query->where('casino_id', $user->casino_id);
            })
            ->orderBy('name')
            ->paginate(10) // 👈 paginamos de 10 en 10
        ->withQueryString(); // mantiene filtros o búsqueda si los agregas luego
           
           
        // Datos para selects
        $casinos = $user->hasRole('master_admin')
            ? Casino::select('id', 'nombre')->get()
            : [];

        $sucursales = $user->hasAnyRole(['master_admin', 'casino_admin'])
            ? Sucursal::select('id', 'nombre', 'casino_id')
            ->when($user->hasRole('casino_admin'), fn($qq) => $qq->where('casino_id', $user->casino_id))
            ->get()
            : [];

        return Inertia::render('Usuarios/Index', [
            'users' => $usuarios,
            'roles' => $roles,
            'casinos'    => $casinos,
            'sucursales' => $sucursales,
            'user'       => $user->only(['id', 'name', 'sucursal_id', 'casino_id']) + ['roles' => $user->getRoleNames()],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|string|exists:roles,name',
            'casino_id' => 'nullable|exists:casinos,id',
            'sucursal_id' => 'nullable|exists:sucursales,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'casino_id' => $validated['casino_id'] ?? null,
            'sucursal_id' => $validated['sucursal_id'] ?? null,
            'activo' => 1,
        ]);

        $user->assignRole($validated['role']);

        return back()->with('success', 'Usuario creado correctamente');
    }



    public function update(Request $request, User $user)
    {        
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'required|string|exists:roles,name',
            'casino_id' => 'nullable|exists:casinos,id',
            'sucursal_id' => 'nullable|exists:sucursales,id',
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'casino_id' => $validated['casino_id'] ?? null,
            'sucursal_id' => $validated['sucursal_id'] ?? null,
        ];

        // Solo actualizar password si se proporcionó
        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        // Sincronizar rol
        $user->syncRoles([$validated['role']]);

        return back()->with('success', 'Usuario actualizado correctamente');
    }





    public function toggleStatus(Request $request, User $user)
{
    $user->activo = $request->input('activo', 0);
    $user->save();

    return back()->with('success', 'Estado actualizado correctamente');
}
}
