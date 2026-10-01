<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\MaintenanceRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('departments.index', [
            'departments' => Department::withCount(['users', 'equipment'])->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('departments.form', ['department' => new Department(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Department::create($this->validated($request));

        return redirect()->route('departments.index')->with('ok', 'Saved');
    }

    public function edit(Department $department): View
    {
        return view('departments.form', ['department' => $department]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validated($request));

        return redirect()->route('departments.index')->with('ok', 'Saved');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $inUse = $department->users()->exists()
            || $department->equipment()->exists()
            || MaintenanceRequest::query()->where('department_id', $department->id)->exists();

        if ($inUse) {
            return redirect()->route('departments.index')->with('err', 'Error_HasRelated');
        }

        $department->delete();

        return redirect()->route('departments.index')->with('ok', 'Deleted');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
