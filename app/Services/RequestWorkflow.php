<?php

namespace App\Services;

use App\Enums\DepartmentConfirmation;
use App\Enums\EquipmentStatus;
use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Events\RequestChanged;
use App\Models\ActivityLog;
use App\Models\ChecklistResult;
use App\Models\Equipment;
use App\Models\MaintenanceRequest;
use App\Models\Priority;
use App\Models\Setting;
use App\Models\SparePart;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Maintenance request lifecycle: creation, status transitions (with timeline) and notifications. */
class RequestWorkflow
{
    public function __construct(private FileUploadService $files, private WebPushService $push) {}

    /** @param list<UploadedFile> $files */
    public function create(?User $user, ?Equipment $equipment, int $departmentId, string $description,
        Priority $priority, array $files = [], bool $preventive = false, ?int $planId = null, ?int $faultTypeId = null,
        bool $foodSafetyImpact = false, ?int $followUpOfId = null, ?Carbon $dueAt = null): MaintenanceRequest
    {
        // A fault on CCP/oPRP equipment always affects food safety; any food-safety fault jumps to the critical priority.
        $foodSafetyImpact = $foodSafetyImpact || filled($equipment?->ccp_reference);
        $escalatedFrom = null;
        if ($foodSafetyImpact) {
            $critical = Priority::criticalForFoodSafety();
            if ($critical !== null && $critical->rank > $priority->rank) {
                $escalatedFrom = $priority;
                $priority = $critical;
            }
        }

        $request = DB::transaction(function () use ($user, $equipment, $departmentId, $description, $priority, $preventive, $planId, $faultTypeId, $foodSafetyImpact, $followUpOfId, $dueAt, $escalatedFrom) {
            $request = MaintenanceRequest::create([
                'equipment_id' => $equipment?->id,
                'department_id' => $departmentId,
                'created_by_id' => $user?->id ?? User::query()->where('role', Role::Admin)->value('id'),
                'description' => $description,
                'priority_id' => $priority->id,
                'due_at' => $dueAt ?? ($priority->sla_hours ? now()->addHours((int) $priority->sla_hours) : null),
                'fault_type_id' => $faultTypeId,
                'status' => RequestStatus::New,
                'is_under_warranty' => $equipment?->isUnderWarranty() ?? false,
                'is_preventive' => $preventive,
                'preventive_maintenance_plan_id' => $planId,
                'food_safety_impact' => $foodSafetyImpact,
                'follow_up_of_id' => $followUpOfId,
            ]);
            $request->update(['request_number' => sprintf('MR-%s-%05d', $request->created_at->format('Y'), $request->id)]);
            $request->timeline()->create([
                'status_from' => null,
                'status_to' => RequestStatus::New,
                'changed_by_id' => $user?->id,
                'changed_at' => now(),
                'note' => $escalatedFrom !== null ? str_replace('{0}', $escalatedFrom->label(), __('FoodSafetyEscalatedNote')) : null,
            ]);

            return $request;
        });

        foreach (array_slice($files, 0, 5) as $file) {
            $saved = $this->files->save($file, 'requests/'.$request->id);
            if ($saved !== null) {
                $request->attachments()->create([
                    'file_url' => $saved['url'],
                    'file_name' => $saved['name'],
                    'uploaded_by_id' => $user?->id,
                ]);
            }
        }

        $request->load(['equipment', 'department', 'priority']);
        ActivityLog::record('request_created', $request, $request->request_number.' — '.Str::limit($description, 80), $user);
        RequestChanged::dispatch($request, true);
        $this->notifyNewRequest($request);
        if ($foodSafetyImpact) {
            $this->notify($request, $this->foodSafetyIds($request->created_by_id), 'Push_FoodSafetyTitle', null, withPriority: true);
        }

        if (Setting::bool(Setting::AUTO_ASSIGN)) {
            $this->autoAssign($request);
        }

        return $request;
    }

    /**
     * Picks the free-est active technician, preferring those whose specialty matches the equipment category,
     * and assigns the new request to them on behalf of the system.
     */
    public function autoAssign(MaintenanceRequest $request): ?User
    {
        $technician = $this->suggestTechnician($request);
        if ($technician === null) {
            return null;
        }

        $this->assign($request, $technician, null, __('AutoAssignedNote'));

        return $technician;
    }

    public function suggestTechnician(MaintenanceRequest $request): ?User
    {
        $category = $request->equipment?->category;
        $active = [RequestStatus::Assigned, RequestStatus::Accepted, RequestStatus::InProgress, RequestStatus::WaitingParts];

        return User::query()
            ->where('role', Role::Technician)->where('is_active', true)
            ->withCount(['assignedRequests as open_tasks' => fn ($q) => $q->whereIn('status', $active)])
            ->get()
            ->sortBy(fn (User $t) => [($category !== null && $t->specialty === $category) ? 0 : 1, $t->open_tasks])
            ->first();
    }

    /** Adds a comment from any party (requester, manager, staff or technician) to the timeline without changing status. */
    public function comment(MaintenanceRequest $request, User $by, string $note): void
    {
        $this->transition($request, $request->status, $by, $note);
    }

    /** Flags open requests past their SLA once, alerting staff and the technician; returns how many were escalated. */
    public function escalateOverdue(): int
    {
        $count = 0;
        MaintenanceRequest::with(['equipment', 'department', 'assignedTechnician', 'priority'])
            ->overdue()->whereNull('escalated_at')->orderBy('due_at')
            ->each(function (MaintenanceRequest $request) use (&$count) {
                $request->forceFill(['escalated_at' => now()])->save();
                RequestChanged::dispatch($request);
                $this->notify($request, [...$this->staffIds(), ...array_filter([$request->assigned_technician_id])], 'Push_OverdueTitle', null, withPriority: true);
                $count++;
            });

        return $count;
    }

    public function transition(MaintenanceRequest $request, RequestStatus $to, ?User $by, ?string $note = null): void
    {
        $from = $request->status;
        if ($to === RequestStatus::Closed && $from !== $to) {
            $request->loadMissing('equipment');
            if ($request->requiresRelease() && ! $request->isReleased()) {
                throw ValidationException::withMessages(['release' => __('Error_ReleaseRequired')]);
            }
        }
        $request->status = $to;
        if ($to === RequestStatus::Closed) {
            $request->closed_at ??= now();
        }

        DB::transaction(function () use ($request, $from, $to, $by, $note) {
            $current = MaintenanceRequest::query()->lockForUpdate()->find($request->id, ['status']);
            if ($current !== null && $current->status !== $from) {
                $request->status = $current->status;
                throw ValidationException::withMessages(['status' => __('Error_StateChanged')]);
            }
            $request->save();
            $request->touch();
            $request->timeline()->create([
                'status_from' => $from,
                'status_to' => $to,
                'changed_by_id' => $by?->id,
                'changed_at' => now(),
                'note' => filled($note) ? trim($note) : null,
            ]);
        });

        ActivityLog::record('request_transition', $request, $request->request_number.': '.$from->value.' → '.$to->value, $by);
        $this->afterTransition($request, $from, $to, $by, $note);
    }

    public function assign(MaintenanceRequest $request, User $technician, ?User $by, ?string $note): void
    {
        $request->assigned_technician_id = $technician->id;
        $request->assigned_at = now();
        $request->setRelation('assignedTechnician', $technician);
        $this->transition($request, RequestStatus::Assigned, $by, $note);
    }

    /**
     * Completes the request: issues spare parts from stock (rejects quantities above the balance), records checklist results,
     * and moves the PM plan forward.
     *
     * @param  array<int, int>  $parts  spare_part_id => quantity
     * @param  array<int, array{compliant: bool, note: ?string}>  $checklist  checklist_item_id => result
     */
    public function complete(MaintenanceRequest $request, User $by, string $resolution, ?string $technicianNotes,
        float $laborCost, array $parts, array $checklist, ?int $faultTypeId = null, ?int $faultCauseId = null,
        bool $temporaryRepair = false, ?Carbon $permanentRepairDue = null): void
    {
        $request->loadMissing('equipment');
        $foodContact = $request->equipment?->food_contact ?? false;

        DB::transaction(function () use ($request, $by, $resolution, $technicianNotes, $laborCost, $parts, $checklist, $faultTypeId, $faultCauseId, $temporaryRepair, $permanentRepairDue, $foodContact) {
            foreach ($parts as $partId => $qty) {
                $qty = (int) $qty;
                $spare = SparePart::query()->lockForUpdate()->find($partId);
                if ($spare === null || $qty <= 0) {
                    continue;
                }
                if ($foodContact && ! $spare->is_food_grade) {
                    throw ValidationException::withMessages(['parts' => str_replace('{0}', $spare->name, __('Error_NonFoodGradePart'))]);
                }
                if ($qty > $spare->quantity) {
                    throw ValidationException::withMessages([
                        'parts' => str_replace(['{0}', '{1}'], [$spare->name, $spare->quantity], __('Error_InsufficientStock')),
                    ]);
                }
                $spare->decrement('quantity', $qty);
                StockMovement::create([
                    'spare_part_id' => $spare->id,
                    'date' => now(),
                    'type' => StockMovementType::Issue,
                    'quantity' => -$qty,
                    'balance_after' => $spare->quantity,
                    'unit_cost' => $spare->unit_cost,
                    'maintenance_request_id' => $request->id,
                    'user_id' => $by->id,
                ]);
                $request->partsUsed()->create([
                    'spare_part_id' => $spare->id,
                    'quantity' => $qty,
                    'unit_cost_at_use' => $spare->unit_cost,
                ]);
            }

            foreach ($checklist as $itemId => $result) {
                ChecklistResult::create([
                    'request_id' => $request->id,
                    'checklist_item_id' => $itemId,
                    'is_compliant' => $result['compliant'],
                    'note' => $result['note'],
                ]);
            }

            $request->fill([
                'resolution_notes' => $resolution,
                'technician_notes' => $technicianNotes,
                'fault_type_id' => $faultTypeId ?? $request->fault_type_id,
                'fault_cause_id' => $faultCauseId,
                'cost_labor' => (float) $request->cost_labor + $laborCost,
                'cost_parts' => (float) $request->partsUsed()->selectRaw('coalesce(sum(quantity * unit_cost_at_use), 0) as total')->value('total'),
                'completed_at' => now(),
                'department_confirmation' => DepartmentConfirmation::Pending,
                'is_temporary_repair' => $temporaryRepair,
                'permanent_repair_due' => $temporaryRepair ? $permanentRepairDue : null,
                'released_at' => null,
                'released_by_id' => null,
                'release_checklist' => null,
                'release_notes' => null,
            ]);

            $equipment = $request->equipment;
            $equipment?->update(['last_maintenance_date' => today()]);
            if ($temporaryRepair && $equipment !== null && $equipment->status === EquipmentStatus::Working) {
                $equipment->update(['status' => EquipmentStatus::WorkingWithIssues]);
            }

            $plan = $request->plan;
            if ($request->is_preventive && $plan !== null) {
                $plan->last_executed_date = today();
                if ($plan->next_due_date->lte(today())) {
                    $plan->next_due_date = today()->addDays($plan->frequency_days);
                    $equipment?->update(['next_maintenance_date' => $plan->next_due_date]);
                }
                $plan->save();
            }

            $this->transition($request, RequestStatus::Completed, $by, $resolution);
        });

        if ($temporaryRepair && $permanentRepairDue !== null) {
            $this->createFollowUp($request, $by, $permanentRepairDue);
        }
    }

    /** A temporary repair is not a fix: the permanent repair becomes its own open request due on the agreed date. */
    private function createFollowUp(MaintenanceRequest $request, User $by, Carbon $permanentRepairDue): MaintenanceRequest
    {
        $followUp = $this->create(
            $by,
            $request->equipment,
            $request->department_id,
            str_replace(['{0}', '{1}'], [$request->request_number, Str::limit($request->resolution_notes ?? $request->description, 300)], __('FollowUpDescription')),
            $request->priority,
            faultTypeId: $request->fault_type_id,
            foodSafetyImpact: $request->food_safety_impact,
            followUpOfId: $request->id,
            dueAt: $permanentRepairDue->copy()->endOfDay(),
        );

        $this->notify($request, array_values(array_unique([...$this->staffIds($by->id), ...$this->foodSafetyIds($by->id)])),
            'Push_TemporaryRepairTitle', str_replace('{0}', $permanentRepairDue->toDateString(), __('TemporaryRepairNote')));

        return $followUp;
    }

    /**
     * Post-maintenance release of food-contact equipment: every checklist point must be confirmed before the request can close.
     *
     * @param  array<string, bool>  $checklist  item key => confirmed
     */
    public function release(MaintenanceRequest $request, User $by, array $checklist, ?string $notes): void
    {
        $missing = array_filter(MaintenanceRequest::RELEASE_CHECKLIST, fn (string $item) => empty($checklist[$item]));
        if ($missing !== []) {
            throw ValidationException::withMessages(['release' => __('Error_ReleaseChecklistIncomplete')]);
        }

        $request->fill([
            'released_at' => now(),
            'released_by_id' => $by->id,
            'release_checklist' => array_fill_keys(MaintenanceRequest::RELEASE_CHECKLIST, true),
            'release_notes' => filled($notes) ? trim($notes) : null,
        ])->save();

        ActivityLog::record('request_released', $request, $request->request_number.' — '.$by->full_name, $by);
        $this->comment($request, $by, __('ReleaseSignedNote').(filled($notes) ? ' — '.trim($notes) : ''));
    }

    /** Food-safety officer's record of what product was affected and what was decided (hold, discard, release). */
    public function recordFoodSafetyDecision(MaintenanceRequest $request, User $by, ?string $affectedProduct, string $decision): void
    {
        $request->fill([
            'food_safety_impact' => true,
            'affected_product' => filled($affectedProduct) ? trim($affectedProduct) : null,
            'food_safety_decision' => trim($decision),
        ])->save();

        ActivityLog::record('food_safety_decision', $request, $request->request_number.' — '.Str::limit($decision, 80), $by);
        $this->comment($request, $by, __('FoodSafetyDecisionNote').': '.trim($decision));
    }

    /**
     * One place decides who hears about every status change, so no party is left out:
     * staff (admin/coordinator), the assigned technician and the requester side (requester + department managers).
     */
    private function afterTransition(MaintenanceRequest $request, RequestStatus $from, RequestStatus $to, ?User $by, ?string $note): void
    {
        $request->loadMissing(['equipment', 'department', 'assignedTechnician', 'priority']);
        RequestChanged::dispatch($request, false, $from === $to ? null : $from);

        $actor = $by?->id;
        $technician = $request->assigned_technician_id;
        $staff = fn () => $request->food_safety_impact
            ? array_values(array_unique([...$this->staffIds($actor), ...$this->foodSafetyIds($actor)]))
            : $this->staffIds($actor);
        $tech = fn () => $technician !== null && $technician !== $actor ? [$technician] : [];
        $requesterSide = fn () => array_values(array_diff($this->requesterSideIds($request), array_filter([$actor])));

        if ($from === $to) {
            $titleKey = $by?->isTechnician() ? 'Push_NoteAddedTitle' : 'Push_CommentTitle';
            $this->notify($request, array_values(array_unique([...$staff(), ...$tech(), ...$requesterSide()])), $titleKey, $note, withTechnician: $by?->isTechnician() ?? false);

            return;
        }

        match ($to) {
            RequestStatus::Assigned => [
                $this->notify($request, $tech(), 'Push_AssignedTitle', $note),
                $this->notify($request, $staff(), 'Push_AssignedStaffTitle', $note, withTechnician: true),
                $this->notify($request, $requesterSide(), 'Push_RequesterAssignedTitle', null, withTechnician: true),
            ],
            RequestStatus::Accepted => $this->notify($request, $staff(), 'Push_AcceptedTitle', $note, withTechnician: true),
            RequestStatus::InProgress => [
                $this->notify($request, $staff(), 'Push_StartedTitle', $note, withTechnician: true),
                $this->notify($request, $requesterSide(), 'Push_StartedTitle', null, withTechnician: true),
            ],
            RequestStatus::WaitingParts => $this->notify($request, $staff(), 'Push_WaitingPartsTitle', $note, withTechnician: true),
            RequestStatus::Completed => [
                $this->notify($request, $staff(), 'Push_CompletedTitle', $note, withTechnician: true),
                $this->notify($request, $requesterSide(), 'Push_ConfirmTitle', $note),
            ],
            RequestStatus::Closed => $this->notify($request, [...$tech(), ...$requesterSide()], 'Push_ClosedTitle', $note),
            RequestStatus::Reopened => $this->notify(
                $request,
                [...$tech(), ...$staff()],
                $request->department_confirmation === DepartmentConfirmation::NotResolved ? 'Push_NotResolvedTitle' : 'Push_ReopenedTitle',
                $note,
            ),
            RequestStatus::Cancelled => $this->notify($request, [...$tech(), ...$staff(), ...$requesterSide()], 'Push_CancelledTitle', $note),
            default => null,
        };
    }

    /**
     * Pushes one request notification; the text is built per recipient language.
     *
     * @param  list<int>  $userIds
     */
    private function notify(MaintenanceRequest $request, array $userIds, string $titleKey, ?string $note, bool $withTechnician = false, bool $withPriority = false): void
    {
        if ($userIds === []) {
            return;
        }

        $this->push->sendLocalized($userIds, function () use ($request, $titleKey, $note, $withTechnician, $withPriority): array {
            $title = __($titleKey).' '.$request->request_number;
            if ($withPriority && ! $request->priority->is_default) {
                $title = $request->priority->label().' — '.$title;
            }

            $what = $request->equipment?->name ?? $request->department?->localized_name;
            $text = Str::limit(filled($note) ? trim($note) : $request->description, 120);
            $body = $what ? "{$what} — {$text}" : $text;
            if ($withTechnician && $request->assignedTechnician !== null) {
                $body = $request->assignedTechnician->full_name.' | '.$body;
            }

            return [$title, $body];
        }, route('requests.show', $request, false), 'request-'.$request->id);
    }

    private function notifyNewRequest(MaintenanceRequest $request): void
    {
        $this->notify($request, $this->staffIds($request->created_by_id), 'Push_NewRequestTitle', null, withPriority: true);
    }

    /** @return list<int> the requester and the active managers of the request's department */
    public function requesterSideIds(MaintenanceRequest $request): array
    {
        return User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereKey($request->created_by_id)
                ->orWhere(fn ($m) => $m->where('role', Role::DepartmentManager)->where('department_id', $request->department_id)))
            ->pluck('id')->all();
    }

    /** @return list<int> active food-safety officers */
    public function foodSafetyIds(?int $except = null): array
    {
        return User::query()
            ->where('role', Role::FoodSafety)
            ->where('is_active', true)
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->pluck('id')->all();
    }

    /** @return list<int> active admins + coordinators */
    public function staffIds(?int $except = null): array
    {
        return User::query()
            ->whereIn('role', [Role::Admin, Role::Coordinator])
            ->where('is_active', true)
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->pluck('id')->all();
    }
}
