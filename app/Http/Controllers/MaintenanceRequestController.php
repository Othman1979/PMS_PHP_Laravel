<?php

namespace App\Http\Controllers;

use App\Enums\DepartmentConfirmation;
use App\Enums\EquipmentStatus;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\SparePart;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\RequestWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceRequestController extends Controller
{
    public function __construct(private RequestWorkflow $workflow) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->isEmployee()) {
            return redirect()->route('quick.find');
        }

        $user = $request->user();
        $status = RequestStatus::tryFrom((string) $request->query('status'));
        $departmentId = $request->integer('department_id') ?: null;
        $createdBy = trim((string) $request->query('user'));

        $requests = MaintenanceRequest::with(['equipment', 'department', 'createdBy', 'assignedTechnician'])
            ->visibleTo($user)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->when($createdBy !== '', fn ($q) => $q->whereHas('createdBy', fn ($u) => $u
                ->where('full_name', 'like', "%{$createdBy}%")->orWhere('username', 'like', "%{$createdBy}%")))
            ->latest()->latest('id')
            ->paginate(50)->withQueryString();

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
            'departments' => Department::query()->orderBy('id')->get(),
        ]);
    }

    public function mine(Request $request): View
    {
        $user = $request->user();
        $active = [RequestStatus::Assigned, RequestStatus::Accepted, RequestStatus::InProgress, RequestStatus::WaitingParts];

        return view('requests.mine', [
            'tasks' => MaintenanceRequest::with(['equipment', 'department'])
                ->where('assigned_technician_id', $user->id)
                ->whereIn('status', $active)
                ->orderByRaw(RequestPriority::orderSql().' desc')->orderBy('assigned_at')
                ->get(),
            'recentDone' => MaintenanceRequest::with(['equipment', 'department'])
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

        return view('requests.create', [
            'departments' => Department::query()->where('is_active', true)
                ->when($user->role === Role::Employee && $user->department_id, fn ($q) => $q->whereKey($user->department_id))
                ->get(),
            'equipment' => Equipment::query()->where('status', '!=', EquipmentStatus::OutOfService)->orderBy('code')->get(),
            'selectedEquipment' => $request->integer('equipment_id') ?: null,
            'defaultDepartment' => $user->department_id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'department_id' => ['required', Rule::exists('departments', 'id')->where('is_active', true)],
            'equipment_id' => ['nullable', 'exists:equipment,id'],
            'description' => ['required', 'string', 'max:2000'],
            'priority' => ['required', Rule::enum(RequestPriority::class)],
            'files' => ['nullable', 'array', 'max:5'],
            'files.*' => [FileUploadService::rule()],
        ]);

        if ($user->role === Role::Employee && $user->department_id && (int) $data['department_id'] !== $user->department_id) {
            abort(403);
        }

        $maintenanceRequest = $this->workflow->create(
            $user,
            isset($data['equipment_id']) ? Equipment::find($data['equipment_id']) : null,
            (int) $data['department_id'],
            trim($data['description']),
            RequestPriority::from($data['priority']),
            $request->file('files', []),
        );

        return redirect()->route('requests.show', $maintenanceRequest)->with('ok', 'Saved');
    }

    public function show(Request $request, MaintenanceRequest $maintenanceRequest): View
    {
        $user = $request->user();
        abort_unless($maintenanceRequest->isVisibleTo($user), 403);

        $maintenanceRequest->load([
            'equipment', 'department', 'createdBy', 'assignedTechnician', 'attachments',
            'timeline' => fn ($q) => $q->with('changedBy')->orderByDesc('changed_at')->orderByDesc('id'),
            'partsUsed.sparePart', 'checklistResults.checklistItem', 'plan.checklist.items',
        ]);

        $category = $maintenanceRequest->equipment?->category;
        $technicians = User::query()->where('role', Role::Technician)->where('is_active', true)->orderBy('full_name')->get()
            ->sortByDesc(fn (User $t) => $category !== null && $t->specialty === $category)->values();

        return view('requests.show', [
            'mr' => $maintenanceRequest,
            'user' => $user,
            'technicians' => $technicians,
            'suggested' => $technicians->first(fn (User $t) => $category !== null && $t->specialty === $category),
            'spareParts' => SparePart::query()->orderBy('name')->get(),
            'checklist' => $maintenanceRequest->plan?->checklist,
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
            $this->workflow->notifyStaff($maintenanceRequest->load('assignedTechnician'), 'Push_AcceptedTitle', $note, $request->user());
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
            $this->workflow->notifyStaff($maintenanceRequest->load('assignedTechnician'), 'Push_StartedTitle', $note, $request->user());
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

        $data = $request->validate([
            'resolution_notes' => ['required', 'string', 'max:2000'],
            'technician_notes' => ['nullable', 'string', 'max:2000'],
            'cost_labor' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'parts' => ['nullable', 'array'],
            'parts.*.spare_part_id' => ['nullable', 'integer', 'exists:spare_parts,id'],
            'parts.*.quantity' => ['nullable', 'integer', 'min:0'],
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
            trim($data['resolution_notes']), $data['technician_notes'] ?? null, (float) ($data['cost_labor'] ?? 0), $parts, $checklist);

        return $this->back($maintenanceRequest);
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
