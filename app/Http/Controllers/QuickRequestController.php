<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentStatus;
use App\Enums\RequestStatus;
use App\Models\Equipment;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Services\RequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Simple phone screen opened by scanning the QR label stuck on the equipment. */
class QuickRequestController extends Controller
{
    public function find(Request $request): View|RedirectResponse
    {
        if ($request->user()->isTechnician()) {
            return redirect()->route('requests.mine');
        }
        $q = trim((string) $request->query('q'));
        if ($q !== '') {
            $exact = Equipment::query()->where('code', $q)->first();
            if ($exact) {
                return redirect()->route('quick.show', $exact->code);
            }
            $results = Equipment::with('department')
                ->where('status', '!=', EquipmentStatus::OutOfService)
                ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('code', 'like', "%{$q}%")->orWhere('location', 'like', "%{$q}%"))
                ->orderBy('code')->take(30)->get();
        } else {
            $departmentId = $request->user()->department_id;
            $results = $departmentId === null ? collect() : Equipment::with('department')
                ->where('department_id', $departmentId)
                ->where('status', '!=', EquipmentStatus::OutOfService)
                ->orderBy('name')->take(50)->get();
        }

        return view('quick.find', [
            'results' => $results,
            'q' => $q,
            'notFound' => false,
            'myRequests' => MaintenanceRequest::with(['equipment', 'assignedTechnician'])
                ->where('created_by_id', $request->user()->id)
                ->whereNotIn('status', RequestStatus::closed())
                ->latest()->take(5)->get(),
        ]);
    }

    public function mine(Request $request): View|RedirectResponse
    {
        if ($request->user()->isTechnician()) {
            return redirect()->route('requests.mine');
        }

        return view('quick.mine', [
            'requests' => MaintenanceRequest::with(['equipment', 'assignedTechnician'])
                ->where('created_by_id', $request->user()->id)
                ->latest()->paginate(20),
        ]);
    }

    public function show(Request $request, string $code): View|RedirectResponse
    {
        if ($request->user()->isTechnician()) {
            return redirect()->route('requests.mine');
        }
        $equipment = $this->findEquipment($code);
        if ($equipment === null) {
            return view('quick.find', ['results' => collect(), 'q' => $code, 'notFound' => true, 'myRequests' => collect()]);
        }

        return view('quick.show', [
            'equipment' => $equipment,
            'openRequests' => $equipment->maintenanceRequests()->with('assignedTechnician')
                ->whereNotIn('status', RequestStatus::closed())
                ->latest()->take(3)->get(),
            'issues' => array_values(array_filter(array_map('trim', explode('|', __('QuickIssues_'.$equipment->category->value))))),
            'priorities' => Priority::query()->active()->where('show_in_quick', true)->ordered()->get(),
            'defaultPriority' => Priority::default(),
            'faultTypes' => FaultType::query()->active()->ordered()->get(),
        ]);
    }

    public function store(Request $request, string $code, RequestWorkflow $workflow): RedirectResponse
    {
        abort_if($request->user()->isTechnician(), 403);
        $equipment = $this->findEquipment($code);
        abort_if($equipment === null, 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'priority_id' => ['nullable', Rule::exists('priorities', 'id')->where('is_active', true)->where('show_in_quick', true)],
            'fault_type_id' => ['nullable', Rule::exists('fault_types', 'id')->where('is_active', true)],
            'food_safety_impact' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', 'max:'.config('cmms.upload_max_kb')],
        ]);

        $maintenanceRequest = $workflow->create(
            $request->user(),
            $equipment,
            $equipment->department_id,
            trim($data['description']),
            isset($data['priority_id']) ? Priority::findOrFail($data['priority_id']) : Priority::default(),
            array_filter([$request->file('photo')]),
            faultTypeId: isset($data['fault_type_id']) ? (int) $data['fault_type_id'] : null,
            foodSafetyImpact: $request->boolean('food_safety_impact'),
        );

        return redirect()->route('quick.done', $maintenanceRequest);
    }

    public function done(Request $request, MaintenanceRequest $maintenanceRequest): View|RedirectResponse
    {
        if ($request->user()->isTechnician()) {
            return redirect()->route('requests.mine');
        }
        abort_unless($maintenanceRequest->isVisibleTo($request->user()), 403);

        return view('quick.done', ['mr' => $maintenanceRequest->load('equipment')]);
    }

    private function findEquipment(string $code): ?Equipment
    {
        return Equipment::with('department')->where('code', trim($code))->first();
    }
}
