<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        // Se mantiene tu lógica de permisos, solo el super_admin puede ver la lista completa.
        if (!Auth::user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $users = User::with(['role:id,display_name', 'area:id,name'])
            ->orderBy('last_name')
            ->get();

        return response()->json(['users' => $users]);
    }
    /**
     * Almacena un nuevo usuario en la base de datos.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        $superAdminRole = Role::where('name', 'super_admin')->first();

        if ($superAdminRole && $request->input('role_id') == $superAdminRole->id) {
            return response()->json(['message' => 'No está permitido crear un nuevo Administrador del Sistema.'], 403);
        }

        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'numero_documento_identidad' => 'required|string|max:12|unique:users',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_id' => 'required|exists:roles,id',
            'area_id' => 'required|exists:areas,id',
            'jefe_area_id' => 'nullable|exists:users,id',
            'cargo' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:12',
            'activo' => 'sometimes|boolean',
        ]);

        $validatedData['password'] = Hash::make($validatedData['password']);
        $user = User::create($validatedData);

        return response()->json([
            'message' => 'Usuario creado exitosamente.',
            'user' => $user->load(['role', 'area'])
        ], 201);
    }

    /**
     * Actualiza un usuario específico.
     */
    public function update(Request $request, User $user)
    {
        if (!Auth::user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        $superAdminRole = Role::where('name', 'super_admin')->first();

        if ($superAdminRole && $request->input('role_id') == $superAdminRole->id && $user->role_id != $superAdminRole->id) {
            return response()->json(['message' => 'No está permitido asignar el rol de Administrador del Sistema a otro usuario.'], 403);
        }

        if ($superAdminRole && $user->role_id == $superAdminRole->id && $request->input('role_id') != $superAdminRole->id) {
            return response()->json(['message' => 'El Administrador del Sistema principal no puede cambiar su rol.'], 403);
        }
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'numero_documento_identidad' => ['required', 'string', 'max:12', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role_id' => 'required|exists:roles,id',
            'area_id' => 'required|exists:areas,id',
            'jefe_area_id' => 'nullable|exists:users,id',
            'cargo' => 'nullable|string|max:255',
            'telefono' => 'nullable|string|max:12',
            'activo' => 'sometimes|boolean',
        ]);

        // Solo actualiza la contraseña si se proporcionó una nueva.
        if (!empty($validatedData['password'])) {
            $validatedData['password'] = Hash::make($validatedData['password']);
        } else {
            unset($validatedData['password']);
        }

        $user->update($validatedData);

        return response()->json([
            'message' => 'Usuario actualizado exitosamente.',
            'user' => $user->load(['role', 'area'])
        ]);
    }
    /**
     * método seguro y ligero para obtener una lista de usuarios
     * para los menús desplegables (selects) en el frontend.
     * * - Seguridad: Solo devuelve los campos no sensibles (id, name, last_name).
     */
    public function listForSelect()
    {
        if (!Auth::user()->hasAnyRole(['super_admin', 'jefe_administracion'])) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $users = User::where('activo', true)
            ->orderBy('name')
            ->get(['id', 'name', 'last_name']);

        return response()->json($users);
    }

    //Devuelve una lista de usuarios con roles de gestión para el filtro del log de auditoría.
    public function listForAuditFilter()
    {
        // 1. Definir los nombres de los roles que consideramos de gestión.
        $managementRoles = ['super_admin', 'jefe_administracion', 'gerente_general'];

        // 2. Buscar los IDs de estos roles en la base de datos.
        $roleIds = Role::whereIn('name', $managementRoles)->pluck('id');

        // 3. Obtener los usuarios que tienen asignado alguno de esos roles.
        $users = User::whereIn('role_id', $roleIds)
            ->where('activo', true)
            ->orderBy('name')
            ->get(['id', 'name', 'last_name']);

        return response()->json($users);
    }
    /**
     * Desactiva un usuario (Soft Delete).
     */
    public function destroy(User $user)
    {
        if (!Auth::user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }
        $superAdminRole = Role::where('name', 'super_admin')->first();
        if ($superAdminRole && $user->role_id == $superAdminRole->id) {
            return response()->json(['message' => 'La cuenta del Administrador del Sistema no puede ser desactivada.'], 403);
        }   
        // Un administrador no puede desactivarse a sí mismo.
        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'No puedes desactivar tu propia cuenta.'], 409);
        }

        $user->activo = false;
        $user->save();

        return response()->json(['message' => 'Usuario desactivado exitosamente.']);
    }

    /**
     * Activa un usuario.
     */
    public function activate(User $user)
    {
        if (!Auth::user()->hasRole('super_admin')) {
            return response()->json(['message' => 'Acción no autorizada.'], 403);
        }

        $user->activo = true;
        $user->save();

        return response()->json(['message' => 'Usuario activado exitosamente.']);
    }
}
