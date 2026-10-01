<?php

namespace App\Http\Controllers;

use App\Enums\PmStrategy;
use App\Models\Checklist;
use App\Models\Equipment;
use App\Models\PreventiveMaintenancePlan;
use App\Services\PmGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PreventiveMaintenanceController extends Controller
{
    public function __construct(private PmGenerator $generator) {}

    public function index(): View
    {
        return view('pm.index', [
            'plans' => PreventiveMaintenancePlan::with(['equipment.department', 'checklist'])->orderBy('next_due_date')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new PreventiveMaintenancePlan([
            'strategy' => PmStrategy::TimeBased, 'frequency_days' => 30, 'next_due_date' => today()->addDays(30), 'is_active' => true,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = PreventiveMaintenancePlan::create($this->validated($request));
        $this->generator->syncEquipmentNextDate($plan->equipment_id);

        return redirect()->route('pm.index')->with('ok', __('Saved'));
    }

    public function edit(PreventiveMaintenancePlan $plan): View
    {
        return $this->form($plan);
    }

    public function update(Request $request, PreventiveMaintenancePlan $plan): RedirectResponse
    {
        $oldEquipment = $plan->equipment_id;
        $plan->update($this->validated($request));
        $this->generator->syncEquipmentNextDate($plan->equipment_id);
        if ($oldEquipment !== $plan->equipment_id) {
            $this->generator->syncEquipmentNextDate($oldEquipment);
        }

        return redirect()->route('pm.index')->with('ok', __('Saved'));
    }

    public function destroy(PreventiveMaintenancePlan $plan): RedirectResponse
    {
        $plan->delete();
        $this->generator->syncEquipmentNextDate($plan->equipment_id);

        return redirect()->route('pm.index')->with('ok', __('Deleted'));
    }

    public function markDone(PreventiveMaintenancePlan $plan): RedirectResponse
    {
        $plan->update(['last_executed_date' => today(), 'next_due_date' => today()->addDays($plan->frequency_days)]);
        $this->generator->syncEquipmentNextDate($plan->equipment_id);

        return redirect()->route('pm.index')->with('ok', __('Saved'));
    }

    public function generate(): RedirectResponse
    {
        $count = $this->generator->generateDue();

        return redirect()->route('pm.index')->with('ok', str_replace('{0}', (string) $count, __('PmGenerated')));
    }

    private function form(PreventiveMaintenancePlan $plan): View
    {
        return view('pm.form', [
            'plan' => $plan,
            'equipment' => Equipment::query()->orderBy('code')->get(['id', 'code', 'name']),
            'checklists' => Checklist::query()->orderBy('id')->get(),
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'equipment_id' => ['required', Rule::exists('equipment', 'id')],
            'strategy' => ['required', Rule::enum(PmStrategy::class)],
            'task_description_en' => ['required', 'string', 'max:500'],
            'task_description_ar' => ['required', 'string', 'max:500'],
            'frequency_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'next_due_date' => ['required', 'date'],
            'checklist_id' => ['nullable', Rule::exists('checklists', 'id')],
        ]);

        return [...$data, 'is_active' => $request->boolean('is_active')];
    }
}
