<?php

namespace App\Models;

use App\Enums\DepartmentConfirmation;
use App\Enums\Permission;
use App\Enums\RequestStatus;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'request_number', 'equipment_id', 'department_id', 'created_by_id', 'description', 'location', 'priority_id', 'fault_type_id', 'fault_cause_id', 'status',
    'assigned_technician_id', 'assigned_at', 'due_at', 'escalated_at', 'accepted_at', 'started_at', 'completed_at', 'closed_at',
    'is_under_warranty', 'is_preventive', 'preventive_maintenance_plan_id', 'cost_labor', 'cost_parts',
    'resolution_notes', 'technician_notes', 'department_confirmation',
    'food_safety_impact', 'affected_product', 'food_safety_decision', 'is_temporary_repair', 'permanent_repair_due', 'follow_up_of_id',
    'released_at', 'released_by_id', 'release_checklist', 'release_notes',
    'archived_at', 'cancelled_at', 'cancelled_by_id', 'cancel_reason',
])]
class MaintenanceRequest extends Model
{
    /** Points signed off before food-contact equipment goes back into production (label keys: Release_<item>). */
    public const RELEASE_CHECKLIST = ['tools_removed', 'cleaned', 'sanitized', 'function_checked'];

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'department_confirmation' => DepartmentConfirmation::class,
            'assigned_at' => 'datetime',
            'due_at' => 'datetime',
            'escalated_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
            'archived_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_under_warranty' => 'boolean',
            'is_preventive' => 'boolean',
            'food_safety_impact' => 'boolean',
            'is_temporary_repair' => 'boolean',
            'permanent_repair_due' => 'date',
            'released_at' => 'datetime',
            'release_checklist' => 'array',
            'cost_labor' => 'decimal:2',
            'cost_parts' => 'decimal:2',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(Priority::class);
    }

    public function faultType(): BelongsTo
    {
        return $this->belongsTo(FaultType::class);
    }

    public function faultCause(): BelongsTo
    {
        return $this->belongsTo(FaultCause::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(RequestTechnician::class, 'request_id')->orderBy('id');
    }

    /** @return Collection<int, RequestTechnician> */
    public function activeTeamMembers(): Collection
    {
        return $this->teamMembers->whereNull('removed_at');
    }

    /** @return list<int> */
    public function technicianIds(): array
    {
        return array_values(array_unique(array_filter([
            $this->assigned_technician_id,
            ...$this->activeTeamMembers()->pluck('user_id')->all(),
        ])));
    }

    public function isLeadTechnician(User $user): bool
    {
        return $user->isTechnician() && $this->assigned_technician_id === $user->id;
    }

    /** @return list<int> */
    public function actualTechnicianIds(): array
    {
        if ($this->teamMembers->isEmpty() && $this->completed_at !== null) {
            return array_values(array_filter([$this->assigned_technician_id]));
        }

        return $this->teamMembers->whereNotNull('worked_at')->pluck('user_id')->all();
    }

    public function hasTechnician(User $user): bool
    {
        return $user->isTechnician() && in_array($user->id, $this->technicianIds(), true);
    }

    #[Scope]
    protected function assignedTo(Builder $query, int $userId): void
    {
        $query->where(fn (Builder $team) => $team
            ->where('assigned_technician_id', $userId)
            ->orWhereHas('teamMembers', fn (Builder $member) => $member->where('user_id', $userId)->whereNull('removed_at')));
    }

    /** The temporary-repair request this follow-up was generated from. */
    public function followUpOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'follow_up_of_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(self::class, 'follow_up_of_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PreventiveMaintenancePlan::class, 'preventive_maintenance_plan_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class, 'request_id');
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(RequestTimeline::class, 'request_id');
    }

    public function partsUsed(): HasMany
    {
        return $this->hasMany(RequestPartUsed::class, 'request_id');
    }

    public function checklistResults(): HasMany
    {
        return $this->hasMany(ChecklistResult::class, 'request_id');
    }

    public function totalCost(): float
    {
        return (float) $this->cost_labor + (float) $this->cost_parts;
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, RequestStatus::closed(), true);
    }

    /** Past the SLA deadline of its priority and still not completed. */
    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->isOpen() && $this->due_at->isPast();
    }

    /** Food-contact equipment must be cleaned, sanitized and signed off before the request can be closed. */
    public function requiresRelease(): bool
    {
        return $this->equipment !== null && $this->equipment->food_contact;
    }

    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    /** Open follow-up (permanent repair) still pending for a temporary repair. */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    public function hasOpenFollowUp(): bool
    {
        return $this->followUps()->whereNotIn('status', RequestStatus::closedValues())->exists();
    }

    /** Admin, food-safety officer or the department's manager may sign the release checklist. */
    public function canRelease(User $user): bool
    {
        return $user->canApproveFoodSafety()
            || ($user->role === Role::DepartmentManager && $this->department_id === $user->department_id);
    }

    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->whereNotNull('due_at')->where('due_at', '<', now())->whereNotIn('status', RequestStatus::closedValues());
    }

    /** Requests a user may see: staff see all, technicians only what is assigned to them, managers their department, others their own. */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        match (true) {
            $user->canWatchAllRequests() => null,
            $user->role === Role::DepartmentManager => $query->where('department_id', $user->department_id),
            $user->role === Role::Technician => $query->assignedTo($user->id),
            default => $query->where('created_by_id', $user->id),
        };
    }

    #[Scope]
    protected function archived(Builder $query, bool $archived = true): void
    {
        $archived ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /** Terminal requests older than the configured retention are archived; the latest terminal timestamp is the reference. */
    #[Scope]
    protected function archivable(Builder $query, int $days): void
    {
        $cutoff = now()->subDays($days);
        $query->whereNull('archived_at')
            ->whereIn('status', RequestStatus::closedValues())
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $c) => $c->where('status', RequestStatus::Cancelled)->where('cancelled_at', '<', $cutoff))
                ->orWhere(fn (Builder $c) => $c->where('status', RequestStatus::Closed)->where('closed_at', '<', $cutoff))
                ->orWhere(fn (Builder $c) => $c->where('status', RequestStatus::Completed)->where('completed_at', '<', $cutoff))
                ->orWhere(fn (Builder $c) => $c->whereNull('cancelled_at')->whereNull('closed_at')->whereNull('completed_at')->where('updated_at', '<', $cutoff)));
    }

    public function isVisibleTo(User $user): bool
    {
        return match (true) {
            $user->canWatchAllRequests() => true,
            $user->role === Role::DepartmentManager => $this->department_id === $user->department_id,
            $user->role === Role::Technician => $this->hasTechnician($user),
            default => $this->created_by_id === $user->id,
        };
    }

    /** Technicians never cancel; staff with the cancel permission may cancel until the request is closed; a requester may only withdraw their own request before work starts. */
    public function canCancel(User $user): bool
    {
        if ($user->isTechnician() || in_array($this->status, [RequestStatus::Closed, RequestStatus::Cancelled], true)) {
            return false;
        }
        if ($user->hasPermission(Permission::RequestsCancel)) {
            return true;
        }

        return $user->hasPermission(Permission::RequestsCancelOwn)
            && $this->created_by_id === $user->id
            && in_array($this->status, [RequestStatus::New, RequestStatus::UnderReview, RequestStatus::Assigned], true);
    }

    public function canReopen(User $user): bool
    {
        return ! $user->isTechnician()
            && in_array($this->status, [RequestStatus::Closed, RequestStatus::Cancelled], true)
            && $user->hasPermission(Permission::RequestsReopen);
    }

    /** Requester or the requester's department manager (and admin) may confirm completion. */
    public function isRequesterSide(User $user): bool
    {
        return $this->created_by_id === $user->id
            || ($user->role === Role::DepartmentManager && $this->department_id === $user->department_id)
            || $user->isAdmin();
    }

    public function isAssignedTo(User $user): bool
    {
        return $user->isAdmin() || $this->assigned_technician_id === $user->id;
    }
}
