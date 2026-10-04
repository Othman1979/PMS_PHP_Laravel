<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shared CRUD for the small admin-managed lists under System Settings
 * (fault types, fault causes, priorities). Subclasses name the model and route prefix.
 */
abstract class LookupController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $routePrefix;

    protected string $titleKey;

    public function index(): View
    {
        return view('settings.lookup-index', [
            'items' => $this->model::query()->withCount('requests')->ordered()->get(),
            'prefix' => $this->routePrefix,
            'titleKey' => $this->titleKey,
        ]);
    }

    public function create(): View
    {
        return $this->form($this->newModel());
    }

    public function store(Request $request): RedirectResponse
    {
        $this->model::create($this->validated($request, null));

        return redirect()->route($this->routePrefix.'.index')->with('ok', 'Saved');
    }

    public function edit(int $id): View
    {
        return $this->form($this->model::query()->findOrFail($id));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $item = $this->model::query()->findOrFail($id);
        $item->update($this->validated($request, $item));

        return redirect()->route($this->routePrefix.'.index')->with('ok', 'Saved');
    }

    public function destroy(int $id): RedirectResponse
    {
        $item = $this->model::query()->findOrFail($id);
        if ($item->requests()->exists()) {
            return redirect()->route($this->routePrefix.'.index')->with('err', 'Error_LookupInUse');
        }
        $item->delete();

        return redirect()->route($this->routePrefix.'.index')->with('ok', 'Deleted');
    }

    protected function form(Model $item): View
    {
        return view('settings.lookup-form', [
            'item' => $item,
            'prefix' => $this->routePrefix,
            'titleKey' => $this->titleKey,
        ]);
    }

    protected function newModel(): Model
    {
        return new $this->model(['is_active' => true]);
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, ?Model $item): array
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_ar' => ['required', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
