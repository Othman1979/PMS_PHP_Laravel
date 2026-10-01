<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentStatus;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
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

        return view('quick.find', ['results' => $results, 'q' => $q, 'notFound' => false]);
    }

    public function show(string $code): View
    {
        $equipment = $this->findEquipment($code);
        if ($equipment === null) {
            return view('quick.find', ['results' => collect(), 'q' => $code, 'notFound' => true]);
        }

        return view('quick.show', [
            'equipment' => $equipment,
            'openRequests' => $equipment->maintenanceRequests()->with('assignedTechnician')
                ->whereNotIn('status', RequestStatus::closed())
                ->latest()->take(3)->get(),
            'issues' => array_values(array_filter(array_map('trim', explode('|', __('QuickIssues_'.$equipment->category->value))))),
        ]);
    }

    public function store(Request $request, string $code, RequestWorkflow $workflow): RedirectResponse
    {
        $equipment = $this->findEquipment($code);
        abort_if($equipment === null, 404);

        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            'priority' => ['nullable', Rule::in([RequestPriority::Normal->value, RequestPriority::Urgent->value, RequestPriority::Critical->value])],
            'photo' => ['nullable', 'image', 'max:'.config('pms.upload_max_kb')],
        ]);

        $maintenanceRequest = $workflow->create(
            $request->user(),
            $equipment,
            $equipment->department_id,
            trim($data['description']),
            RequestPriority::tryFrom($data['priority'] ?? '') ?? RequestPriority::Normal,
            array_filter([$request->file('photo')]),
        );

        return redirect()->route('quick.done', $maintenanceRequest);
    }

    public function done(Request $request, MaintenanceRequest $maintenanceRequest): View
    {
        abort_unless($maintenanceRequest->isVisibleTo($request->user()), 403);

        return view('quick.done', ['mr' => $maintenanceRequest->load('equipment')]);
    }

    private function findEquipment(string $code): ?Equipment
    {
        return Equipment::with('department')->where('code', trim($code))->first();
    }
}
