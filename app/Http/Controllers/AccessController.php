<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessController extends Controller
{
    public function index(): View
    {
        return view('access.index', [
            'roles' => Role::with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get()->groupBy(fn ($permission) => explode('.', $permission->name)[0]),
            'users' => User::with('roles')->orderBy('name')->paginate(15),
        ]);
    }

    public function saveRole(Request $request, ?Role $role = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role?->id)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')],
        ]);
        if ($role?->name === 'Administrator') {
            abort(403, 'Role Administrator dilindungi.');
        }
        $role ??= Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $role->update(['name' => $data['name']]);
        $role->syncPermissions($data['permissions'] ?? []);

        return back()->with('success', 'Role dan izin berhasil disimpan.');
    }

    public function saveUser(Request $request, ?User $user = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')],
        ]);
        $roles = $data['roles'] ?? [];
        if ($user?->id === $request->user()->id && ! in_array('Administrator', $roles, true)) {
            return back()->withErrors(['roles' => 'Anda tidak dapat melepas role Administrator milik sendiri.']);
        }
        $attributes = ['name' => $data['name'], 'email' => $data['email']];
        if (filled($data['password'] ?? null)) {
            $attributes['password'] = Hash::make($data['password']);
        }
        $user ??= new User;
        $user->fill($attributes)->save();
        $user->syncRoles($roles);

        return back()->with('success', 'Akun dan role berhasil disimpan.');
    }
}
