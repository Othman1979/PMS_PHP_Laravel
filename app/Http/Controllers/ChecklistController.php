<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentCategory;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistResult;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChecklistController extends Controller
{
    public function index(): View
    {
        return view('checklists.index', ['checklists' => Checklist::withCount('items')->orderBy('id')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $checklist = Checklist::create($this->validated($request));

        return redirect()->route('checklists.edit', $checklist)->with('ok', __('Saved'));
    }

    public function edit(Checklist $checklist): View
    {
        return view('checklists.edit', ['checklist' => $checklist->load('items')]);
    }

    public function update(Request $request, Checklist $checklist): RedirectResponse
    {
        $checklist->update($this->validated($request));

        return redirect()->route('checklists.edit', $checklist)->with('ok', __('Saved'));
    }

    public function destroy(Checklist $checklist): RedirectResponse
    {
        $inUse = $checklist->plans()->exists()
            || ChecklistResult::query()->whereIn('checklist_item_id', $checklist->items()->select('id'))->exists();
        if ($inUse) {
            return redirect()->route('checklists.index')->with('err', __('Error_InUse'));
        }

        $checklist->delete();

        return redirect()->route('checklists.index')->with('ok', __('Deleted'));
    }

    public function addItem(Request $request, Checklist $checklist): RedirectResponse
    {
        $data = $request->validate([
            'text_en' => ['required', 'string', 'max:500'],
            'text_ar' => ['required', 'string', 'max:500'],
        ]);
        $checklist->items()->create([...$data, 'sort_order' => ((int) $checklist->items()->max('sort_order')) + 1]);

        return redirect()->route('checklists.edit', $checklist);
    }

    public function deleteItem(Checklist $checklist, ChecklistItem $item): RedirectResponse
    {
        abort_unless($item->checklist_id === $checklist->id, 404);
        if (ChecklistResult::query()->where('checklist_item_id', $item->id)->exists()) {
            return redirect()->route('checklists.edit', $checklist)->with('err', __('Error_InUse'));
        }
        $item->delete();

        return redirect()->route('checklists.edit', $checklist);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:200'],
            'name_ar' => ['required', 'string', 'max:200'],
            'category' => ['nullable', Rule::enum(EquipmentCategory::class)],
        ]);
    }
}
