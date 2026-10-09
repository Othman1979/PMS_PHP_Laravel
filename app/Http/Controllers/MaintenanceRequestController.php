<?php

namespace App\Http\Controllers;

use App\Enums\DepartmentConfirmation;
use App\Enums\EquipmentStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\FaultCause;
use App\Models\FaultType;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\SparePart;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\RequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MaintenanceRequestController extends Controller
{
    public function __construct(private RequestWorkflow $workflow) {}

    private const FILTER_KEYS = ['status', 'department_id', 'user', 'overdue', 'food_safety', 'q'];

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->isEmployee()) {
            return redirect()->route('quick.find');
        }
        if ($request->user()->isTechnician()) {
            return redirect()->route('requests.mine');
        }

        $user = $request->user();
        $filters = $this->rememberFilters($request);
        if ($filters instanceof RedirectResponse) {
            return $filters;
        }

        $status = RequestStatus::tryFrom((string) ($filters['status'] ?? ''));
        $departmentId = (int) ($filters['department_id'] ?? 0) ?: null;
        $createdBy = trim((string) ($filters['user'] ?? ''));
        $overdue = (bool) ($filters['overdue'] ?? false);
        $foodSafety = (bool) ($filters['food_safety'] ?? false);
        $search = trim((string) ($filters['q'] ?? ''));

        $base = MaintenanceRequest::query()->visibleTo($user)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($createdBy !== '', fn ($q) => $q->whereHas('createdBy', fn ($u) => $u
                ->where('full_name', 'like', "%{$createdBy}%")->orWhere('username', 'like', "%{$createdBy}%")))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('request_number', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('equipment', fn ($e) => $e->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))));

        $counts = (clone $base)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')
            ->mapWithKeys(fn ($c, $status) => [$status instanceof RequestStatus ? $status->value : (string) $status => (int) $c]);
        $overdueCount = (clone $base)->overdue()->count();
        $foodSafetyCount = (clone $base)->where('food_safety_impact', true)->whereNotIn('status', RequestStatus::closedValues())->count();

        $requests = (clone $base)->with(['equipment', 'department', 'createdBy', 'assignedTechnician', 'priority'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($overdue, fn ($q) => $q->overdue())
            ->when($foodSafety, fn ($q) => $q->where('food_safety_impact', true)->whereNotIn('status', RequestStatus::closedValues()))
            ->latest()->latest('id')
            ->paginate(50)->appends($filters);

        $title = match ($user->role) {
            Role::Employee => __('MyRequests'),
            Role::DepartmentManager => __('DepartmentRequests'),
            default => __('AllRequests'),
        };

        return view('requests.index', [
            'requests' => $requests,
            'title' => $title,
            'status' => $status,
            'departmentId' => $departmentId,
            'createdBy' => $createdBy,
            'overdue' => $overdue,
            'foodSafety' => $foodSafety,
            'foodSafetyCount' => $foodSafetyCount,
            'search' => $search,
            'filters' => $filters,
            'counts' => $counts,
            'overdueCount' => $overdueCount,
            'technicians' => $request->user()->canManage() ? User::query()->where('role', Role::Technician)->where('is_active', true)->orderBy('full_name')->get(['id', 'full_name']) : collect(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name_en')->get(),
        ]);
    }

    /**
     * Keeps the last used filters in the session so coming back to the list restores them; an explicit
     * `reset` query clears them. Returns a redirect when stored filters should be re-applied.
     *
     * @return array<string, string>|RedirectResponse
     */
    private function rememberFilters(Request $request): array|RedirectResponse
    {
        $key = 'requests.filters';
        if ($request->has('reset')) {
            $request->session()->forget($key);

            return redirect()->route('requests.index');
        }

        $given = array_filter($request->only(self::FILTER_KEYS), fn ($v) => $v !== null && $v !== '');
        if ($given === [] && ! $request->has('page') && ! $request->boolean('all')) {
            $saved = $request->session()->get($key, []);
            if ($saved !== []) {
                return redirect()->route('requests.index', $saved);
            }
        }

        $request->session()->put($key, $given);

        return array_map(fn ($v) => (string) $v, $given);
    }

    public function mine(Request $request): View
    {
        $user = $request->user();
        $active = [RequestStatus::Assigned, RequestStatus::Accepted, RequestStatus::InProgress, RequestStatus::WaitingParts];

        return view('requests.mine', [
            'tasks' => MaintenanceRequest::with(['equipment', 'department', 'priority'])
                ->where('assigned_technician_id', $user->id)
                ->whereIn('status', $active)
                ->orderByRaw('case when due_at is not null and due_at < ? then 0 else 1 end', [now()])
                ->orderByRaw(Priority::rankSql().' desc')->orderBy('due_at')->orderBy('assigned_at')
                ->get(),
            'recentDone' => MaintenanceRequest::with(['equipment', 'department', 'priority'])
                ->where('assigned_technician_id', $user->id)
                ->whereIn('status', [RequestStatus::Completed, RequestStatus::Closed])
                ->latest('completed_at')->take(5)->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user->isEmployee()) {
            return redirect()->route('quick.find');
        }
        if ($user->isTechnician()) {
            return redirect()->route('requests.mine');
        }

        return view('requests.create', [
            'departments' => Department::query()->where('is_active', true)
                ->when($user->role === Role::Employee && $user->department_id, fn ($q) => $q->whereKey($user->department_id))
                ->get(),
            'equipment' => Equipment::query()->where('status', '!=', EquipmentStatus::OutOfService)->orderBy('code')->get(),
            'selectedEquipment' => $request->integer('equipment_id') ?: null,
            'defaultDepartment' => $user->department_id,
            'priorities' => Priority::activeOrdered(),
            'defaultPriority' => Priority::default(),
            'faultTypes' => FaultType::query()->active()->ordered()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isTechnician(), 403);
        $data = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
            'equipment_id' => ['nullable', 'exists:equipment,id'],
            'description' => ['required', 'string', 'max:2000'],
            'priority_id' => ['required', Rule::exists('priorities', 'id')->where('is_active', true)],
            'fault_type_id' => ['nullable', Rule::exists('fault_types', 'id')->where('is_active', true)],
            'food_safety_impact' => ['nullable', 'boolean'],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => FileUploadService::rules(),
        ]);

        if ($user->role === Role::Employee && $user->department_id && (int) $data['department_id'] !== $user->department_id) {
            abort(403);
        }

        $maintenanceRequest = $this->workflow->create(
            $user,
            isset($data['equipment_id']) ? Equipment::find($data['equipment_id']) : null,
            (int) $data['department_id'],
            trim($data['description']),
            Priority::findOrFail($data['priority_id']),
            $request->file('files', []),
            faultTypeId: isset($data['fault_type_id']) ? (int) $data['fault_type_id'] : null,
            foodSafetyImpact: $request->boolean('food_safety_impact'),
        );

        return redirect()->route('requests.show', $maintenanceRequest)->with('ok', 'Saved');
    }

    public function show(Request $request, MaintenanceRequest $maintenanceRequest): View
    {
        $user = $request->user();
        abort_unless($maintenanceRequest->isVisibleTo($user), 403);

        $maintenanceRequest->load([
            'equipment', 'department', 'createdBy', 'assignedTechnician', 'attachments', 'priority', 'faultType', 'faultCause',
            'timeline' => fn ($q) => $q->with('changedBy')->orderByDesc('changed_at')->orderByDesc('id'),
            'partsUsed.sparePart', 'checklistResults.checklistItem', 'plan.checklist.items',
            'releasedBy', 'followUpOf', 'followUps' => fn ($q) => $q->latest(),
        ]);

        $category = $maintenanceRequest->equipment?->category;
        $technicians = $user->canManage()
            ? User::query()->where('role', Role::Technician)->where('is_active', true)->orderBy('full_name')->get()
                ->sortByDesc(fn (User $t) => $category !== null && $t->specialty === $category)->values()
            : collect();

        return view('requests.show', [
            'mr' => $maintenanceRequest,
            'user' => $user,
            'technicians' => $technicians,
            'suggested' => $technicians->first(fn (User $t) => $category !== null && $t->specialty === $category),
            'spareParts' => SparePart::query()
                ->when($maintenanceRequest->equipment?->food_contact, fn ($q) => $q->where('is_food_grade', true))
                ->orderBy('name')->get(),
            'checklist' => $maintenanceRequest->plan?->checklist,
            'faultTypes' => FaultType::query()->active()->ordered()->get(),
            'faultCauses' => FaultCause::query()->active()->ordered()->get(),
        ]);
    }

    public function review(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        if (in_array($maintenanceRequest->status, [RequestStatus::New, RequestStatus::Reopened], true)) {
            $this->workflow->transition($maintenanceRequest, RequestStatus::UnderReview, $request->user());
        }

        return $this->back($maintenanceRequest);
    }

    public function assign(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $data = $request->validate([
            'technician_id' => ['required', Rule::exists('users', 'id')->where('role', Role::Technician->value)->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (in_array($maintenanceRequest->status, [RequestStatus::New, RequestStatus::UnderReview, RequestStatus::Reopened], true)) {
            $this->workflow->assign($maintenanceRequest, User::findOrFail($data['technician_id']), $request->user(), $data['note'] ?? null);
        }

        return $this->back($maintenanceRequest);
    }

    public function accept(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorizeTechnician($request, $maintenanceRequest);
        if ($maintenanceRequest->status === RequestStatus::Assigned) {
            $note = $this->note($request);
            $maintenanceRequest->accepted_at = now();
            $this->workflow->transition($maintenanceRequest, RequestStatus::Accepted, $request->user(), $note);
        }

        return $this->back($maintenanceRequest);
    }

    /** One tap for the technician: accept the assignment and start working on it right away. */
    public function acceptStart(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorizeTechnician($request, $maintenanceRequest);
        if ($maintenanceRequest->status === RequestStatus::Assigned) {
            $maintenanceRequest->accepted_at = now();
            $this->workflow->transition($maintenanceRequest, RequestStatus::Accepted, $request->user());
            $maintenanceRequest->started_at ??= now();
            $this->workflow->transition($maintenanceRequest, RequestStatus::InProgress, $request->user(), $this->note($request));
        }

        return $this->back($maintenanceRequest);
    }

    /** Any party who can see the request may leave a comment; everyone else involved is notified. */
    public function comment(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        abort_unless($maintenanceRequest->isVisibleTo($request->user()), 403);
        $data = $request->validate(['note' => ['required', 'string', 'max:1000']]);
        if (! in_array($maintenanceRequest->status, [RequestStatus::Cancelled], true)) {
            $this->workflow->comment($maintenanceRequest, $request->user(), trim($data['note']));
        }

        return $this->back($maintenanceRequest);
    }

    public function start(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorizeTechnician($request, $maintenanceRequest);
        if ($maintenanceRequest->status === RequestStatus::Accepted) {
            $note = $this->note($request);
            $maintenanceRequest->started_at ??= now();
            $this->workflow->transition($maintenanceRequest, RequestStatus::InProgress, $request->user(), $note);
        }

        return $this->back($maintenanceRequest);
    }

    public function addNote(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorizeTechnician($request, $maintenanceRequest);
        $note = $this->note($request);
        $active = [RequestStatus::Accepted, RequestStatus::InProgress, RequestStatus::WaitingParts];
        if ($note !== null && in_array($maintenanceRequest->status, $active, true)) {
            $this->workflow->transition($maintenanceRequest, $maintenanceRequest->status, $request->user(), $note);
        }

        return $this->back($maintenanceRequest);
    }

    public function waitParts(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorizeTechnician($request, $maintenanceRequest);
        if ($maintenanceRequest->status === RequestStatus::InProgress) {
            $this->workflow->transition($maintenanceRequest, RequestStatus::WaitingParts, $request->user(), $this->note($request));
        }

        return $this->back($maintenanceRequest);
    }

    public function resume(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorizeTechnician($request, $maintenanceRequest);
        if ($maintenanceRequest->status === RequestStatus::WaitingParts) {
            $this->workflow->transition($maintenanceRequest, RequestStatus::InProgress, $request->user());
        }

        return $this->back($maintenanceRequest);
    }

    public function complete(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorizeTechnician($request, $maintenanceRequest);
        if (! in_array($maintenanceRequest->status, [RequestStatus::InProgress, RequestStatus::WaitingParts], true)) {
            return $this->back($maintenanceRequest);
        }

        $hasTypes = FaultType::query()->active()->exists();
        $hasCauses = FaultCause::query()->active()->exists();
        $data = $request->validate([
            'fault_type_id' => [$hasTypes ? 'required' : 'nullable', Rule::exists('fault_types', 'id')->where('is_active', true)],
            'fault_cause_id' => [$hasCauses ? 'required' : 'nullable', Rule::exists('fault_causes', 'id')->where('is_active', true)],
            'resolution_notes' => ['required', 'string', 'max:2000'],
            'technician_notes' => ['nullable', 'string', 'max:2000'],
            'cost_labor' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'parts' => ['nullable', 'array'],
            'parts.*.spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'parts.*.quantity' => ['nullable', 'integer', 'min:0'],
            'is_temporary_repair' => ['nullable', 'boolean'],
            'permanent_repair_due' => ['nullable', 'required_if_accepted:is_temporary_repair', 'date', 'after:'.today()->toDateString()],
        ]);

        $parts = [];
        foreach ($data['parts'] ?? [] as $line) {
            if (! empty($line['spare_part_id']) && ! empty($line['quantity'])) {
                $parts[(int) $line['spare_part_id']] = ($parts[(int) $line['spare_part_id']] ?? 0) + (int) $line['quantity'];
            }
        }

        $checklist = [];
        foreach ($maintenanceRequest->plan?->checklist?->items ?? [] as $item) {
            $checklist[$item->id] = [
                'compliant' => $request->boolean('check.'.$item->id),
                'note' => $request->input('check_note.'.$item->id),
            ];
        }

        $this->workflow->complete($maintenanceRequest->load(['equipment', 'plan', 'assignedTechnician']), $request->user(),
            trim($data['resolution_notes']), $data['technician_notes'] ?? null, (float) ($data['cost_labor'] ?? 0), $parts, $checklist,
            isset($data['fault_type_id']) ? (int) $data['fault_type_id'] : null,
            isset($data['fault_cause_id']) ? (int) $data['fault_cause_id'] : null,
            $request->boolean('is_temporary_repair'),
            $request->boolean('is_temporary_repair') ? Carbon::parse($data['permanent_repair_due']) : null);

        return $this->back($maintenanceRequest);
    }

    /** Post-maintenance release sign-off for food-contact equipment (manager / food-safety officer / admin). */
    public function release(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($maintenanceRequest->canRelease($user), 403);
        $maintenanceRequest->load('equipment');
        if ($maintenanceRequest->status !== RequestStatus::Completed || ! $maintenanceRequest->requiresRelease() || $maintenanceRequest->isReleased()) {
            return $this->back($maintenanceRequest);
        }

        $data = $request->validate([
            'release' => ['nullable', 'array'],
            'release.*' => ['boolean'],
            'release_notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $checklist = [];
        foreach (MaintenanceRequest::RELEASE_CHECKLIST as $item) {
            $checklist[$item] = $request->boolean('release.'.$item);
        }

        $this->workflow->release($maintenanceRequest, $user, $checklist, $data['release_notes'] ?? null);

        return $this->back($maintenanceRequest)->with('ok', __('ReleaseSigned'));
    }

    public function foodSafety(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->canApproveFoodSafety(), 403);
        $data = $request->validate([
            'affected_product' => ['nullable', 'string', 'max:500'],
            'food_safety_decision' => ['required', 'string', 'max:1000'],
        ]);

        $this->workflow->recordFoodSafetyDecision($maintenanceRequest, $user, $data['affected_product'] ?? null, $data['food_safety_decision']);

        return $this->back($maintenanceRequest)->with('ok', __('Saved'));
    }

    public function confirm(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        abort_unless($maintenanceRequest->isRequesterSide($request->user()), 403);
        if ($maintenanceRequest->status === RequestStatus::Completed) {
            $resolved = $request->boolean('resolved');
            $maintenanceRequest->department_confirmation = $resolved ? DepartmentConfirmation::Resolved : DepartmentConfirmation::NotResolved;
            $this->workflow->transition($maintenanceRequest, $resolved ? RequestStatus::Closed : RequestStatus::Reopened, $request->user());
        }

        return $this->back($maintenanceRequest);
    }

    /** Coordinator/admin bulk action over the selected rows; rows in a non-eligible state are skipped. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['assign', 'cancel', 'close'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'technician_id' => ['required_if:action,assign', 'nullable', Rule::exists('users', 'id')->where('role', Role::Technician->value)->where('is_active', true)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $technician = $data['action'] === 'assign' ? User::findOrFail($data['technician_id']) : null;
        $done = 0;
        $requests = MaintenanceRequest::query()->visibleTo($user)->whereIn('id', $data['ids'])->get();
        foreach ($requests as $mr) {
            try {
                $done += (int) match ($data['action']) {
                    'assign' => $this->bulkAssign($mr, $technician, $user, $data['note'] ?? null),
                    'cancel' => $this->bulkTransition($mr, RequestStatus::Cancelled, [RequestStatus::New, RequestStatus::UnderReview, RequestStatus::Assigned, RequestStatus::Accepted, RequestStatus::InProgress, RequestStatus::WaitingParts, RequestStatus::Reopened], $user, $data['note'] ?? null),
                    'close' => $this->bulkTransition($mr, RequestStatus::Closed, [RequestStatus::Completed, RequestStatus::Reopened], $user, $data['note'] ?? null),
                };
            } catch (ValidationException) {
                // concurrent change — leave the row as-is and report it as skipped
            }
        }

        return redirect()->route('requests.index')->with('ok', __('BulkDone', ['count' => $done, 'skipped' => count($data['ids']) - $done]));
    }

    private function bulkAssign(MaintenanceRequest $mr, User $technician, User $by, ?string $note): bool
    {
        if (! in_array($mr->status, [RequestStatus::New, RequestStatus::UnderReview, RequestStatus::Reopened], true)) {
            return false;
        }
        $this->workflow->assign($mr, $technician, $by, $note);

        return true;
    }

    /** @param  list<RequestStatus>  $eligible */
    private function bulkTransition(MaintenanceRequest $mr, RequestStatus $to, array $eligible, User $by, ?string $note): bool
    {
        if (! in_array($mr->status, $eligible, true)) {
            return false;
        }
        if ($to === RequestStatus::Closed) {
            $mr->closed_at = now();
        }
        $this->workflow->transition($mr, $to, $by, $note);

        return true;
    }

    public function close(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        if (in_array($maintenanceRequest->status, [RequestStatus::Completed, RequestStatus::Reopened], true)) {
            $maintenanceRequest->closed_at = now();
            $this->workflow->transition($maintenanceRequest, RequestStatus::Closed, $request->user());
        }

        return $this->back($maintenanceRequest);
    }

    public function reopen(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($maintenanceRequest->isRequesterSide($user) || $user->canManage(), 403);
        if ($maintenanceRequest->status === RequestStatus::Closed) {
            $maintenanceRequest->fill(['closed_at' => null, 'completed_at' => null, 'department_confirmation' => DepartmentConfirmation::Pending]);
            $this->workflow->transition($maintenanceRequest, RequestStatus::Reopened, $user, $this->note($request));
        }

        return $this->back($maintenanceRequest);
    }

    public function cancel(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($maintenanceRequest->isRequesterSide($user) || $user->canManage(), 403);
        if (! in_array($maintenanceRequest->status, [RequestStatus::Closed, RequestStatus::Cancelled, RequestStatus::Completed], true)) {
            $this->workflow->transition($maintenanceRequest, RequestStatus::Cancelled, $user, $this->note($request));
        }

        return $this->back($maintenanceRequest);
    }

    private function authorizeTechnician(Request $request, MaintenanceRequest $maintenanceRequest): void
    {
        abort_unless($maintenanceRequest->isAssignedTo($request->user()), 403);
    }

    private function note(Request $request): ?string
    {
        $note = trim((string) $request->input('note'));

        return $note === '' ? null : mb_substr($note, 0, 1000);
    }

    private function back(MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        return redirect()->route('requests.show', $maintenanceRequest);
    }
}
