<?php

namespace App\Http\Controllers\Api;

use App\Enums\EquipmentStatus;
use App\Enums\RequestStatus;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\SparePart;
use App\Services\FileUploadService;
use App\Services\RequestWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RequestsController extends Controller
{
    public function __construct(private RequestWorkflow $workflow) {}

    public function configuration(): JsonResponse
    {
        $options = config('reverb.apps.apps.0.options', []);
        $key = config('reverb.apps.apps.0.key');

        return response()->json([
            'pollSeconds' => 15,
            'websocket' => config('broadcasting.default') === 'reverb' && filled($key) ? [
                'key' => $key,
                'host' => $options['host'],
                'port' => (int) $options['port'],
                'scheme' => $options['scheme'] === 'https' ? 'wss' : 'ws',
            ] : null,
        ]);
    }

    public function equipment(Request $request): JsonResponse
    {
        abort_if($request->user()->isTechnician(), 403);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:200']]);
        $q = trim($data['q'] ?? '');
        $items = Equipment::with('department')->where('status', '!=', EquipmentStatus::OutOfService)
            ->when($q !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%")->orWhere('location', 'like', "%{$q}%")))
            ->when($q === '', fn ($query) => $query->where('department_id', $request->user()->department_id))
            ->orderBy('code')->limit(50)->get();

        return response()->json(['items' => $items->map(fn (Equipment $equipment) => [
            'id' => $equipment->id, 'code' => $equipment->code, 'name' => $equipment->name,
            'departmentEn' => $equipment->department?->name_en, 'departmentAr' => $equipment->department?->name_ar,
            'location' => $equipment->location, 'category' => $equipment->category->value,
            'serial' => $equipment->serial_number, 'autoEscalates' => $equipment->autoEscalatesFoodSafety(),
        ])]);
    }

    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'priorities' => Priority::active()->where('show_in_quick', true)->ordered()->get()
                ->map(fn (Priority $item) => ['id' => $item->id, 'en' => $item->name_en, 'ar' => $item->name_ar]),
            'defaultPriorityId' => Priority::default()->id,
            'defaultDepartmentId' => $request->user()->department_id,
            'departments' => Department::active()->ordered()
                ->when($request->user()->isEmployee() && $request->user()->department_id,
                    fn ($query) => $query->whereKey($request->user()->department_id))
                ->get(['id', 'name_en as en', 'name_ar as ar']),
            'faultTypes' => FaultType::active()->ordered()->get(['id', 'name_en as en', 'name_ar as ar']),
            'faultCauses' => FaultCause::active()->ordered()->get(['id', 'name_en as en', 'name_ar as ar']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_if($request->user()->isTechnician(), 403);
        $data = $request->validate([
            'equipment_id' => ['nullable', 'integer', Rule::exists('equipment', 'id')->whereNot('status', EquipmentStatus::OutOfService->value)],
            'department_id' => ['required_without:equipment_id', 'nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'location' => ['required_without:equipment_id', 'nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'priority_id' => ['nullable', Rule::exists('priorities', 'id')->where('is_active', true)->where('show_in_quick', true)],
            'fault_type_id' => ['nullable', Rule::exists('fault_types', 'id')->where('is_active', true)],
            'food_safety_impact' => ['nullable', 'boolean'],
            'photo' => ['nullable', 'image', ...FileUploadService::rules()],
        ]);
        $equipment = isset($data['equipment_id']) ? Equipment::findOrFail($data['equipment_id']) : null;
        $departmentId = $equipment?->department_id ?? (int) $data['department_id'];
        if ($equipment === null && $request->user()->isEmployee() && $request->user()->department_id !== null) {
            abort_unless($departmentId === $request->user()->department_id, 403);
        }
        $mr = $this->workflow->create($request->user(), $equipment, $departmentId,
            trim($data['description']), isset($data['priority_id']) ? Priority::findOrFail($data['priority_id']) : Priority::default(),
            array_filter([$request->file('photo')]),
            faultTypeId: isset($data['fault_type_id']) ? (int) $data['fault_type_id'] : null,
            foodSafetyImpact: $request->boolean('food_safety_impact'), location: $data['location'] ?? null);

        return response()->json(['request' => TasksController::taskPayload($mr)], 201);
    }

    public function show(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        abort_unless($maintenanceRequest->isVisibleTo($request->user()), 403);
        $mr = $maintenanceRequest->load(['timeline.changedBy', 'attachments', 'plan.checklist.items', 'partsUsed.sparePart']);
        $data = TasksController::taskPayload($mr);
        $data['costLabor'] = $mr->cost_labor;
        $data['costParts'] = $mr->cost_parts;
        $data['resolution'] = $mr->resolution_notes;
        $data['phone'] = $mr->createdBy?->phone;
        $data['canManageWorkflow'] = $mr->isLeadTechnician($request->user());
        $data['timeline'] = $mr->timeline->sortByDesc('id')->values()->map(fn ($entry) => [
            'status' => $entry->status_to->value, 'note' => $entry->note,
            'by' => $entry->changedBy?->full_name, 'at' => $entry->changed_at->format('Y-m-d H:i'),
        ]);
        $data['attachments'] = $mr->attachments->map(fn ($file) => ['name' => $file->file_name, 'url' => url($file->file_url)]);
        $data['checklist'] = $mr->plan?->checklist?->items->map(fn ($item) => [
            'id' => $item->id, 'en' => $item->text_en, 'ar' => $item->text_ar,
        ]) ?? [];
        $data['partsUsed'] = $mr->partsUsed->map(fn ($line) => [
            'name' => $line->sparePart?->name, 'quantity' => $line->quantity, 'unitCost' => $line->unit_cost_at_use,
        ]);
        $data['spareParts'] = $request->user()->isTechnician() ? SparePart::where('quantity', '>', 0)
            ->when($mr->equipment?->food_contact, fn ($query) => $query->where('is_food_grade', true))
            ->orderBy('name')->get(['id', 'name', 'quantity', 'unit_cost']) : [];

        return response()->json(['request' => $data]);
    }

    public function action(Request $request, MaintenanceRequest $maintenanceRequest, string $action): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $maintenanceRequest, $action, $data) {
            $mr = $this->lockedAssignment($request, $maintenanceRequest);
            [$from, $to] = match ($action) {
                'accept', 'accept-start' => [RequestStatus::Assigned, RequestStatus::Accepted],
                'start' => [RequestStatus::Accepted, RequestStatus::InProgress],
                'wait-parts' => [RequestStatus::InProgress, RequestStatus::WaitingParts],
                'resume' => [RequestStatus::WaitingParts, RequestStatus::InProgress],
            };
            abort_unless($mr->status === $from, 409, __('Error_StateChanged'));
            if ($to === RequestStatus::Accepted) {
                $mr->accepted_at = now();
            }
            if ($to === RequestStatus::InProgress) {
                $mr->started_at ??= now();
            }
            $this->workflow->transition($mr, $to, $request->user(), $data['note'] ?? null);
            if ($action === 'accept-start') {
                $mr->started_at ??= now();
                $this->workflow->transition($mr, RequestStatus::InProgress, $request->user());
            }
        });

        return $this->show($request, $maintenanceRequest->fresh());
    }

    public function comment(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        abort_unless($maintenanceRequest->isVisibleTo($request->user()), 403);
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        abort_if($maintenanceRequest->status === RequestStatus::Cancelled, 409, __('Error_StateChanged'));
        $this->workflow->comment($maintenanceRequest, $request->user(), trim($data['note']));

        return $this->show($request, $maintenanceRequest->fresh());
    }

    public function participate(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        $this->workflow->confirmParticipation($maintenanceRequest, $request->user());

        return $this->show($request, $maintenanceRequest->fresh());
    }

    public function complete(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        DB::transaction(function () use ($request, $maintenanceRequest) {
            $mr = $this->lockedAssignment($request, $maintenanceRequest);
            abort_unless(in_array($mr->status, [RequestStatus::InProgress, RequestStatus::WaitingParts], true), 409, __('Error_StateChanged'));
            $data = $request->validate([
                'fault_type_id' => [FaultType::active()->exists() ? 'required' : 'nullable', Rule::exists('fault_types', 'id')->where('is_active', true)],
                'fault_cause_id' => [FaultCause::active()->exists() ? 'required' : 'nullable', Rule::exists('fault_causes', 'id')->where('is_active', true)],
                'resolution_notes' => ['required', 'string', 'max:2000'],
                'worked_technician_ids' => ['nullable', 'array', 'min:1', 'max:100'],
                'worked_technician_ids.*' => ['required', 'integer', 'distinct'],
                'technician_notes' => ['nullable', 'string', 'max:2000'],
                'cost_labor' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
                'parts' => ['nullable', 'array', 'max:100'],
                'parts.*.spare_part_id' => ['required', 'integer', 'exists:spare_parts,id', 'distinct'],
                'parts.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
                'check' => ['nullable', 'array'],
                'check.*' => ['boolean'],
                'check_note' => ['nullable', 'array'],
                'check_note.*' => ['nullable', 'string', 'max:1000'],
                'is_temporary_repair' => ['nullable', 'boolean'],
                'permanent_repair_due' => ['nullable', 'required_if_accepted:is_temporary_repair', 'date', 'after:'.today()->toDateString()],
            ]);
            $parts = [];
            foreach ($data['parts'] ?? [] as $line) {
                $parts[(int) $line['spare_part_id']] = (int) $line['quantity'];
            }
            $checklist = [];
            foreach ($mr->plan?->checklist?->items ?? [] as $item) {
                $checklist[$item->id] = ['compliant' => $request->boolean('check.'.$item->id), 'note' => $data['check_note'][$item->id] ?? null];
            }
            $this->workflow->complete($mr, $request->user(), trim($data['resolution_notes']), $data['technician_notes'] ?? null,
                (float) ($data['cost_labor'] ?? 0), $parts, $checklist,
                isset($data['fault_type_id']) ? (int) $data['fault_type_id'] : null,
                isset($data['fault_cause_id']) ? (int) $data['fault_cause_id'] : null,
                $request->boolean('is_temporary_repair'),
                $request->boolean('is_temporary_repair') ? Carbon::parse($data['permanent_repair_due']) : null,
                $data['worked_technician_ids'] ?? null);
        });

        return $this->show($request, $maintenanceRequest->fresh());
    }

    private function lockedAssignment(Request $request, MaintenanceRequest $maintenanceRequest): MaintenanceRequest
    {
        $mr = MaintenanceRequest::lockForUpdate()->findOrFail($maintenanceRequest->id);
        abort_unless($mr->isLeadTechnician($request->user()), 403);

        return $mr;
    }
}
