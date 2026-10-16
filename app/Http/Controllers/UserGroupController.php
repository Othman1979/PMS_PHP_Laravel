<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\UserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserGroupController extends Controller
{
    public function index(): View
    {
        return view('groups.index', [
            'groups' => UserGroup::query()->with('permissions')->withCount('users')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new UserGroup, []);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $group = UserGroup::create($data['attributes']);
        $group->syncPermissions($data['permissions']);
        ActivityLog::record('group_created', $group, $group->name_en.' ['.implode(', ', $data['permissions']).']');

        return redirect()->route('groups.index')->with('ok', 'Saved');
    }

    public function edit(UserGroup $group): View
    {
        return $this->form($group, $group->permissionValues());
    }

    public function update(Request $request, UserGroup $group): RedirectResponse
    {
        $data = $this->validated($request);
        $group->update($data['attributes']);
        $group->syncPermissions($data['permissions']);
        ActivityLog::record('group_updated', $group, $group->name_en.' ['.implode(', ', $data['permissions']).']');

        return redirect()->route('groups.index')->with('ok', 'Saved');
    }

    public function destroy(UserGroup $group): RedirectResponse
    {
        if ($group->default_for_role !== null) {
            return redirect()->route('groups.index')->with('error', __('Error_DefaultGroupLocked'));
        }
        ActivityLog::record('group_deleted', $group, $group->name_en);
        $group->delete();
        UserGroup::flushCache();

        return redirect()->route('groups.index')->with('ok', 'Deleted');
    }

    /** @param  list<string>  $selected */
    private function form(UserGroup $group, array $selected): View
    {
        return view('groups.form', [
            'group' => $group,
            'modules' => Permission::byModule(),
            'selected' => old('permissions', $selected),
        ]);
    }

    /** @return array{attributes: array<string, mixed>, permissions: list<string>} */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:100'],
            'name_en' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ]);

        return [
            'attributes' => ['name_ar' => $data['name_ar'], 'name_en' => $data['name_en'], 'description' => $data['description'] ?? null],
            'permissions' => array_values(array_unique($data['permissions'] ?? [])),
        ];
    }
}
