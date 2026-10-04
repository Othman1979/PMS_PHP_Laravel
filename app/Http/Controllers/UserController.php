<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentCategory;
use App\Enums\Role;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::with('department')->orderBy('role')->orderBy('full_name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new User(['role' => Role::Employee, 'is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        User::create($data);

        return redirect()->route('users.index')->with('ok', 'Saved');
    }

    public function edit(User $user): View
    {
        return $this->form($user);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if ($user->is($request->user())) {
            $data['role'] = Role::Admin;
            $data['is_active'] = true;
        }

        $user->update($data);

        return redirect()->route('users.index')->with('ok', 'Saved');
    }

    private function form(User $user): View
    {
        return view('users.form', [
            'user' => $user,
            'departments' => Department::active()->ordered()->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'role' => ['required', Rule::enum(Role::class)],
            'specialty' => ['nullable', Rule::enum(EquipmentCategory::class)],
            'password' => [$user === null ? 'required' : 'nullable', 'string', 'min:'.($request->input('role') === Role::Employee->value ? 4 : 8)],
            'is_active' => ['boolean'],
        ]);
        $data['username'] = trim($data['username']);
        $data['is_active'] = $request->boolean('is_active');
        if ($data['role'] !== Role::Technician->value) {
            $data['specialty'] = null;
        }

        return $data;
    }
}
