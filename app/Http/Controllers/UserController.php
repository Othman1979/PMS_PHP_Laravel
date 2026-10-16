<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentCategory;
use App\Enums\Role;
use App\Enums\TechnicianTrade;
use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        return view('users.index', [
            'users' => User::with(['department', 'groups'])->visibleTo($request->user())->orderBy('role')->orderBy('full_name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new User(['role' => Role::Employee, 'is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $groups = $data['groups'] ?? [];
        unset($data['groups']);
        $user = User::create($data);
        $groups === [] ? $user->attachDefaultGroup() : $user->groups()->sync($groups);
        ActivityLog::record('user_created', $user, $user->username.' ('.$user->role->value.') '.__('Groups').': '.$this->groupNames($user));

        return redirect()->route('users.index')->with('ok', 'Saved');
    }

    public function edit(Request $request, User $user): View
    {
        $this->ensureVisible($request, $user);

        return $this->form($user);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureVisible($request, $user);
        $data = $this->validated($request, $user);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if ($user->is($request->user())) {
            $data['role'] = Role::Admin;
            $data['is_active'] = true;
        }

        $groups = $data['groups'] ?? [];
        unset($data['groups']);
        $user->update($data);
        if ($user->is($request->user()) && $groups === []) {
            $groups = $user->groups()->pluck('user_groups.id')->all();
        }
        $changes = $user->groups()->sync($groups);
        $user->forgetPermissions();
        $groupsChanged = $changes['attached'] !== [] || $changes['detached'] !== [];
        ActivityLog::record('user_updated', $user, $user->username.' ('.$user->role->value.')'.($groupsChanged ? ' '.__('Groups').': '.$this->groupNames($user) : ''));

        return redirect()->route('users.index')->with('ok', 'Saved');
    }

    /** Developer accounts are hidden from regular admins as if they did not exist. */
    private function ensureVisible(Request $request, User $user): void
    {
        abort_if($user->isDeveloper() && ! $request->user()->isDeveloper(), 404);
    }

    private function groupNames(User $user): string
    {
        return $user->groups()->get()->map(fn (UserGroup $g) => $g->name_en)->implode(', ') ?: '-';
    }

    private function form(User $user): View
    {
        return view('users.form', [
            'user' => $user,
            'departments' => Department::active()->ordered()->get(),
            'groups' => UserGroup::query()->with('permissions')->orderBy('id')->get(),
            'selectedGroups' => old('groups', $user->exists ? $user->groups()->pluck('user_groups.id')->all() : []),
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
            'trade' => ['nullable', Rule::enum(TechnicianTrade::class)],
            'password' => [$user === null ? 'required' : 'nullable', 'string', 'min:4'],
            'is_active' => ['boolean'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['integer', Rule::exists('user_groups', 'id')],
        ]);
        $data['username'] = trim($data['username']);
        $data['is_active'] = $request->boolean('is_active');
        if ($data['role'] !== Role::Technician->value) {
            $data['specialty'] = null;
            $data['trade'] = null;
        }

        return $data;
    }
}
